<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0">
@php
    $font = "'Segoe UI',Arial,sans-serif";
    $signatureStyle = "margin:0;font-family:$font;font-size:16px;line-height:25px;color:#293544;font-weight:400";
    $detailStyle = "margin:0;font-family:$font;font-size:13px;line-height:20px;color:#293544;font-weight:400";
@endphp
<div style="font-family:{{ $font }};font-size:16px;line-height:25px;color:#293544;text-align:left">
    <style>
        @media only screen and (max-width:480px){.outer-pad{padding:18px 12px 24px!important}.email-pad{padding-left:24px!important;padding-right:24px!important}.email-title{font-size:28px!important;line-height:34px!important}}
    </style>
    <table data-school-email-layout="v2" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#F6F4EC;border-collapse:collapse">
        <tr><td class="outer-pad" align="center" style="padding:24px 16px 32px">
            <!--[if mso]><table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:640px;margin:0 auto;background:#ffffff;border-collapse:collapse;table-layout:fixed;text-align:left">
                <tr><td class="email-pad" style="padding:32px 36px 0;text-align:left">
                    <h1 data-email-subject="true" class="email-title" style="margin:0;font-family:{{ $font }};font-size:23px;line-height:30px;font-weight:600;color:#006B78;overflow-wrap:anywhere;word-break:break-word">{{ $subject }}</h1>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px"><tr>
                        <td align="left" valign="middle">
                            <table role="presentation" width="56" cellpadding="0" cellspacing="0" border="0"><tr>
                                <td width="56" height="3" bgcolor="#006B78" style="width:56px;height:3px;background:#006B78;font-size:0;line-height:0">&nbsp;</td>
                            </tr></table>
                        </td>
                        <td align="right" valign="middle">
                            <table data-email-palette="energie" role="presentation" width="72" cellpadding="0" cellspacing="0" border="0"><tr>
                                @foreach (['#006B78', '#3458AE', '#D85E4C', '#E6BD48', '#293544', '#F6F4EC'] as $color)
                                    <td width="12" height="8" bgcolor="{{ $color }}" style="width:12px;height:8px;background:{{ $color }};font-size:0;line-height:0">&nbsp;</td>
                                @endforeach
                            </tr></table>
                        </td>
                    </tr></table>
                </td></tr>
                <tr><td class="email-pad" align="left" style="padding:28px 36px 0;text-align:left;font-size:16px;line-height:25px;overflow-wrap:anywhere">
                    <p style="margin:0 0 15px;font-family:{{ $font }};font-size:16px;line-height:25px;color:#293544;text-align:left">{{ $salutation }}</p>
                    @isset($paragraphs)
                        @foreach ($paragraphs as $part)
                            <p style="margin:0 0 15px;font-family:{{ $font }};font-size:16px;line-height:25px;color:#293544;text-align:left;white-space:pre-line">{{ $part }}</p>
                        @endforeach
                        @if (!empty($portalUrl))
                            <p style="margin:0"><a style="color:#006B78" href="{{ $portalUrl }}">Meine Aufgaben öffnen</a></p>
                        @endif
                        @foreach ($testLinks ?? [] as $testLink)
                            <p style="margin:0"><a style="color:#006B78" href="{{ $testLink['url'] }}">Multiple-Choice-Test öffnen · {{ $testLink['title'] }}</a></p>
                        @endforeach
                    @else
                        <p style="margin:0;font-family:{{ $font }};font-size:16px;line-height:25px;color:#293544;text-align:left">{{ $paragraph }}</p>
                    @endisset
                </td></tr>
                <tr><td class="email-pad" align="left" style="padding:28px 36px 30px;text-align:left">
                    <p data-signature-greeting="true" style="{{ $signatureStyle }};margin-bottom:10px">{{ $signature[0] }}</p>
                    <p data-signature-name="true" style="{{ $signatureStyle }}"><strong style="font-weight:700">{{ $signature[1] }}</strong></p>
                    @foreach (array_slice($signature, 2) as $line)
                        <p data-signature-detail="true" style="{{ $detailStyle }}">{{ $line }}</p>
                    @endforeach
                    <p data-email-attachments="true" style="{{ $signatureStyle }};margin-top:22px;overflow-wrap:anywhere;word-break:break-word"><span style="text-decoration:underline">{{ count($attachments ?? []) === 1 ? 'Anhang' : 'Anhänge' }}</span>: {{ count($attachments ?? []) ? implode(', ', $attachments) : 'keine' }}</p>
                </td></tr>
                <tr><td class="email-pad" style="padding:0 36px 24px">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                        <td height="1" bgcolor="#006B78" style="height:1px;font-size:0;line-height:0;background:#006B78">&nbsp;</td>
                    </tr></table>
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</div>
</body>
</html>
