@props(['url','logo'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
            <img src="http://localhost:8000/storage/images/cdg.png" alt="Logo" width="200"
                height="100" style="max-width:200px !important; max-height:100px !important;">
            @else
            {!! $slot !!}
            @endif
        </a>
    </td>
</tr>