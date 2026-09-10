<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($icon)
@case('organizations')<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 8h.01M15 8h.01M9 12h.01M15 12h.01"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5v1"/>@break
@case('roles')<path d="m12 3 8 3v5c0 5-4 8-8 10-4-2-8-5-8-10V6zM8 12l3 3 5-6"/>@break
@case('audit')<path d="M8 3h8v4H8zM8 5H5v16h14V5h-3M8 11h8M8 15h5M8 18h3"/>@break
@case('memberships')<rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M6 16c1-3 5-3 6 0M15 10h3M15 14h3"/>@break
@case('governance')<path d="m3 8 9-5 9 5M4 8h16M6 8v10M10 8v10M14 8v10M18 8v10M3 21h18M4 18h16"/>@break
{{-- IUOAMC_LEGACY_CERTIFICATE_ICON_1_0_0 --}}
@case('legacy-certificates')<path d="M6 3h12v12H6zM9 7h6M9 10h4"/><circle cx="12" cy="16" r="3"/><path d="m10 18-1 4 3-2 3 2-1-4"/>@break
@case('public-content')<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
@endswitch
</svg>
