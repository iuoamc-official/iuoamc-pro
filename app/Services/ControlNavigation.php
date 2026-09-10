<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;

final class ControlNavigation
{
    public function languageUrl(Request $request, string $locale): string
    {
        abort_unless(in_array($locale, ['ar','en','fr'], true), 404);
        $current = $request->route();
        if (!$current instanceof RoutingRoute || !$current->getName()
            || !in_array('GET', $current->methods(), true)
            || !in_array('locale', $current->parameterNames(), true)) {
            return route('dashboard', ['locale'=>$locale]);
        }
        $parameters = array_replace($current->parameters(), ['locale'=>$locale]);
        $query = [];
        // IUOAMC_LEGACY_CERTIFICATE_FILTER_1_0_0
        foreach (['q','status','organization_id','page','ambiguity','scope'] as $key) {
            $value = $request->query($key);
            if (!array_key_exists($key, $parameters) && is_scalar($value) && strlen((string)$value) <= 160) {
                $query[$key] = (string)$value;
            }
        }
        return route($current->getName(), $parameters + $query);
    }
}
