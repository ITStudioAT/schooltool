<div style="font-family:Arial,Helvetica,sans-serif;color:#263835;line-height:1.6;text-align:left">
    <table data-school-email-layout="v1" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f4f7f5">
        <tr><td align="left" style="padding:16px 0;text-align:left">
            <table role="presentation" align="left" width="620" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:620px;margin:0;background:#ffffff;border:1px solid #d7e3dc;border-radius:12px;text-align:left">
                <tr><td height="5" style="height:5px;background:#2e6655;line-height:5px">&nbsp;</td></tr>
                <tr><td align="left" style="padding:28px 28px 8px;text-align:left">{!! $body !!}</td></tr>
                @if ($signature !== '')
                    <tr><td align="left" style="padding:22px 28px 10px;background:#eef4f1;border-top:1px solid #d7e3dc;text-align:left">{!! $signature !!}</td></tr>
                @endif
            </table>
        </td></tr>
    </table>
</div>
