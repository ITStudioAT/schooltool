<header style="width:90%; padding:6px 0; margin-bottom:10px;
               font-family:Arial,Helvetica,sans-serif; font-size:10px; margin:auto">
    <div style="display:flex; justify-content:space-between; align-items:center;
                border-bottom:1px solid #000; padding-bottom:4px;">
        <h1 style="font-size:10px; margin:0;">{{ $title ?? '' }}</h1>
        <span>{{ now()->format('d.m.Y H:i') }}</span>
    </div>
</header>