@php($appName = config('app.name'))
<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $appName }}</title></head>
<body style="margin:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                <tr><td style="background:#243a5c;color:#ffffff;padding:20px 24px;">
                    <div style="font-size:20px;font-weight:800;">GoMarket</div>
                    <div style="font-size:13px;opacity:0.85;margin-top:2px;">{{ $appName }}</div>
                </td></tr>
                <tr><td style="padding:24px;">
                    <h1 style="margin:0 0 8px 0;font-size:20px;font-weight:800;color:#0f172a;">
                        {{ $matches->count() }} {{ $matches->count() === 1 ? 'new match' : 'new matches' }} for "{{ $search->name }}"
                    </h1>
                    <p style="margin:0 0 16px 0;color:#475569;font-size:14px;line-height:1.5;">
                        {{ $summary }}
                    </p>

                    @foreach ($matches->take(6) as $m)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0;padding-top:14px;margin-top:14px;">
                            <tr>
                                @php($cover = $m->media->first())
                                <td width="80" style="vertical-align:top;padding-right:12px;">
                                    @if ($cover)
                                        <img src="{{ asset('storage/' . ltrim($cover->path, '/')) }}" alt="" width="72" height="72" style="display:block;width:72px;height:72px;object-fit:cover;border-radius:8px;background:#e2e8f0;">
                                    @else
                                        <div style="width:72px;height:72px;border-radius:8px;background:#e2e8f0;"></div>
                                    @endif
                                </td>
                                <td style="vertical-align:top;">
                                    <a href="{{ route('marketplace.show', ['slug' => $m->slug]) }}"
                                       style="color:#243a5c;font-weight:700;font-size:15px;text-decoration:none;line-height:1.3;">
                                        {{ $m->title }}
                                    </a>
                                    <div style="color:#0f172a;font-weight:700;font-size:14px;margin-top:4px;">
                                        {{ $m->formattedPrice() }}
                                    </div>
                                    <div style="color:#64748b;font-size:12px;margin-top:2px;">
                                        {{ collect([$m->city, $m->region, $m->country])->filter()->implode(' • ') }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    @endforeach

                    <div style="text-align:center;margin-top:24px;">
                        <a href="{{ $matchesUrl }}" style="display:inline-block;background:#243a5c;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:999px;">
                            View all {{ $matches->count() }} matches
                        </a>
                    </div>
                </td></tr>
                <tr><td style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:11px;text-align:center;">
                    You're getting this because you turned on email alerts for this saved search.<br>
                    <a href="{{ route('marketplace.saved') }}" style="color:#243a5c;text-decoration:underline;">Manage your saved searches</a>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body></html>
