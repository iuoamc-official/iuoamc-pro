<?php
declare(strict_types=1);
namespace App\Http\Controllers\Control;
use App\Http\Controllers\Controller;
use App\Models\ProCertificate;
use App\Services\ProCertificateCatalog;
use App\Services\ProCertificateRegistry;
use App\Services\ProCertificateBatchRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
final class ProCertificateBatchController extends Controller
{
    private function service(): ProCertificateBatchRegistry { return app(ProCertificateBatchRegistry::class); }
    private function registry(): ProCertificateRegistry { return app(ProCertificateRegistry::class); }
    private function catalog(): ProCertificateCatalog { return app(ProCertificateCatalog::class); }
    private function page(string $view,array $data=[]): Response {
        return response()->view('control.pro_certificates.batches.'.$view,$data,200,
            ['Cache-Control'=>'private, no-store, max-age=0','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow']);
    }
    private function types(Request $request) {
        return $this->catalog()->query($request->user())->where('active',true)->with('organization')->orderBy('name_'.app()->getLocale())->get()
            ->filter(fn($type)=>$this->catalog()->verify($type)&&$type->organization?->status==='active');
    }
    public function index(Request $request): Response {
        $batches=$this->service()->query($request->user())->with('organization')->latest('id')->paginate(20);
        $checks=$batches->getCollection()->mapWithKeys(fn($batch)=>[$batch->id=>$this->service()->verify($batch)]);
        return $this->page('index',compact('batches','checks'));
    }
    public function create(Request $request): Response {
        $this->registry()->requirePermission($request->user(),'certificates.manage');
        $choice=$request->validate(['type_id'=>['nullable','integer','min:1'],'language'=>['nullable','in:ar,en,fr']]);
        $types=$this->types($request);$selectedType=null;$common=[];
        if (!empty($choice['type_id'])) {
            $selectedType=$types->firstWhere('id',(int)$choice['type_id']);abort_unless($selectedType,404);
            $common=$this->catalog()->defaults($request->user(),(int)$selectedType->id,$choice['language']??app()->getLocale());
        }
        return $this->page('form',compact('types','selectedType','common')+['rows'=>null,'ticket'=>null,'requestKey'=>(string)Str::uuid()]);
    }
    private function parseRows(string $text): array {
        if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$text))throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.rows_control')]);
        $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$text));
        if(array_shift($lines)!=="recipient_name\tpublic_name\tspecialization")throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.rows_header')]);
        $rows=[];
        foreach($lines as $line){
            if(trim($line)==='')continue;
            $columns=explode("\t",$line);
            if(count($columns)===2)$columns[]=''; // TrimStrings removes an empty last cell's trailing tab.
            if(count($columns)!==3)throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.rows_columns')]);
            $rows[]=array_combine(['recipient_name','public_name','specialization'],array_map('trim',$columns));
        }
        if(count($rows)<1||count($rows)>100)throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.rows_count')]);
        return $rows;
    }
    public function store(Request $request): Response|RedirectResponse {
        $this->registry()->requirePermission($request->user(),'certificates.manage');
        $input=$request->validate(['intent'=>['required','in:preview,create'],'request_key'=>['required','uuid'],
            'batch_name'=>['required','string','max:120'],'catalog_type_id'=>['required','integer','min:1'],
            'catalog_version'=>['required','integer','min:1'],'confirm_save'=>$request->input('intent')==='create'?['required','accepted']:['nullable'],
            'language'=>['required','in:ar,en,fr'],'recipient_rows'=>['required','string','max:100000'],
            'preview_ticket'=>['nullable','string','max:4096']]);
        $types=$this->types($request);$selectedType=$types->firstWhere('id',(int)$input['catalog_type_id']);abort_unless($selectedType,404);
        if((int)$input['catalog_version']!==(int)$selectedType->lock_version)throw ValidationException::withMessages(['catalog_version'=>__('certificate_catalog.errors.preview_changed')]);
        $common=array_merge($this->catalog()->defaults($request->user(),(int)$selectedType->id,$input['language']),
            $request->only(['batch_name','program_title','achievement_date','expires_on','certificate_title','statement','signatory_name','signatory_title']));
        unset($common['certificate_type']);
        $common['organization_id']=(int)$selectedType->organization_id;$common['catalog_type_id']=(int)$selectedType->id;$common['catalog_version']=(int)$input['catalog_version'];
        $rawRows=$this->parseRows($input['recipient_rows']);$rows=[];$seen=[];
        foreach($rawRows as $offset=>$row){
            $profile=$common;unset($profile['batch_name'],$profile['catalog_version']);
            try{$normalized=$this->registry()->validateDraft($request->user(),array_merge($profile,$row));}
            catch(ValidationException $error){throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.batch_row_number').' '.($offset+1).': '.implode(' ',array_merge(...array_values($error->errors())))]);}
            $row=['recipient_name'=>$normalized['recipient_name'],'public_name'=>$normalized['public_name'],'specialization'=>$normalized['specialization']??null];
            $key=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            if(isset($seen[$key]))throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.batch_duplicate')]);
            $seen[$key]=true;$rows[]=$row;
        }
        $digest=hash('sha256',json_encode([$common,$rows,$input['request_key']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        if($input['intent']==='preview'){
            $ticket=Crypt::encryptString(json_encode(['actor'=>(int)$request->user()->id,'expires'=>time()+1800,'digest'=>$digest],JSON_THROW_ON_ERROR));
            // Flash values only into this request so the explicit preview accurately preserves every field.
            $request->session()->flashInput($request->except(['_token','preview_ticket']));
            return $this->page('form',compact('types','selectedType','common','rows','ticket')+['requestKey'=>$input['request_key']]);
        }
        try{$proof=json_decode(Crypt::decryptString($input['preview_ticket']??''),true,8,JSON_THROW_ON_ERROR);}
        catch(Throwable){throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.preview_required')]);}
        if(!is_array($proof)||($proof['actor']??null)!==(int)$request->user()->id||!is_int($proof['expires']??null)||$proof['expires']<time())
            throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.preview_expired')]);
        if(!is_string($proof['digest']??null)||!hash_equals($digest,$proof['digest']))throw ValidationException::withMessages(['recipient_rows'=>__('certificate_catalog.errors.preview_changed')]);
        $batch=$this->service()->prepare($request->user(),$common,$rows,$input['request_key']);
        return redirect()->route('certificates.batches.show',['locale'=>app()->getLocale(),'batch'=>$batch->id])->with('success',__('certificate_catalog.batch_created'));
    }
    public function show(Request $request): Response {
        $batch=$this->service()->view($request->user(),(int)$request->route('batch'));
        $review=$this->service()->review($request->user(),$batch);
        $members=$review['members'];$fingerprint=$review['fingerprint'];
        $checks=$members->mapWithKeys(fn($record)=>[$record->id=>$this->registry()->verify($record)]);
        $statuses=$members->mapWithKeys(fn($record)=>[$record->id=>$this->registry()->effectiveStatus($record)]);
        $run=$this->service()->latestRun($request->user(),$batch);
        $eligible=['submit'=>$members->where('status','draft')->count(),'approve'=>$members->where('status','review')->count(),'issue'=>$members->where('status','approved')->count()];
        return $this->page('show',compact('batch','members','fingerprint','checks','statuses','run','eligible'));
    }
    public function start(Request $request): RedirectResponse {
        $data=$request->validate(['action'=>['required','in:submit,approve,issue'],'fingerprint'=>['required','string','size:64','regex:/^[a-f0-9]{64}$/'],'confirm'=>['required','accepted']]);
        $run=$this->service()->start($request->user(),(int)$request->route('batch'),$data['action'],$data['fingerprint'],true);
        return redirect()->route('certificates.batches.show',['locale'=>app()->getLocale(),'batch'=>$run->batch_id])->with('certificate_run_started',(int)$run->id);
    }
    public function step(Request $request): JsonResponse {
        $result=$this->service()->step($request->user(),(int)$request->route('run'));
        // Never emit private exception text or raw plan data, including future additions.
        $safe=['done'=>(int)($result['done']??0),'total'=>(int)($result['total']??0),'completed'=>(bool)($result['completed']??false),
            'stopped'=>(bool)($result['stopped']??false),'run_id'=>(int)$request->route('run')];
        return response()->json($safe,200,['Cache-Control'=>'private, no-store, max-age=0','Referrer-Policy'=>'no-referrer']);
    }
}
