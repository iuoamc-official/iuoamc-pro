<?php
declare(strict_types=1);
namespace App\Http\Controllers\Control;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProCertificateType;
use App\Services\InstitutionalAccess;
use App\Services\ProCertificateCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
final class ProCertificateCatalogController extends Controller
{
    private function service(): ProCertificateCatalog { return app(ProCertificateCatalog::class); }
    private function page(string $view, array $data=[]): Response {
        return response()->view('control.pro_certificates.catalog.'.$view,$data,200,
            ['Cache-Control'=>'private, no-store, max-age=0','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow']);
    }
    private function organizations(Request $request) {
        return app(InstitutionalAccess::class)->constrainOrganizations(Organization::query(),$request->user())
            ->where('status','active')->orderBy('display_name')->get(['id','display_name']);
    }
    public function index(Request $request): Response {
        $types=$this->service()->query($request->user())->with('organization')->orderByDesc('id')->paginate(20);
        $checks=$types->getCollection()->mapWithKeys(fn($type)=>[$type->id=>$this->service()->verify($type)]);
        return $this->page('index',compact('types','checks'));
    }
    public function create(Request $request): Response {
        $this->service()->requirePermission($request->user());
        return $this->page('form',['type'=>new ProCertificateType(['active'=>true,'category'=>'participation','layout'=>'classic']),
            'organizations'=>$this->organizations($request)]);
    }
    public function store(Request $request): RedirectResponse {
        $type=$this->service()->create($request->user(),$this->profile($request));
        return redirect()->route('certificates.catalog.edit',['locale'=>app()->getLocale(),'type'=>$type->id])->with('success',__('certificate_catalog.catalog_saved'));
    }
    public function edit(Request $request): Response {
        $this->service()->requirePermission($request->user());
        $type=$this->service()->query($request->user())->with('organization')->findOrFail((int)$request->route('type'));
        abort_unless($this->service()->verify($type),409,__('certificates.errors.integrity'));
        return $this->page('form',['type'=>$type,'organizations'=>$this->organizations($request)]);
    }
    public function update(Request $request): RedirectResponse {
        $version=$request->validate(['lock_version'=>['required','integer','min:1']]);
        $type=$this->service()->update($request->user(),(int)$request->route('type'),(int)$version['lock_version'],$this->profile($request));
        return redirect()->route('certificates.catalog.edit',['locale'=>app()->getLocale(),'type'=>$type->id])->with('success',__('certificate_catalog.catalog_saved'));
    }
    private function profile(Request $request): array {
        $data=$request->only(['organization_id','code','number_prefix','name_ar','name_en','name_fr','category','layout',
            'title_ar','title_en','title_fr','statement_ar','statement_en','statement_fr','signatory_name','signatory_title']);
        $request->validate(['active'=>['required','boolean']]);
        $data['active']=$request->boolean('active');
        return $data;
    }
}
