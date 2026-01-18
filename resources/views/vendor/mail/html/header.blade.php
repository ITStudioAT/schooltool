@props(['url','logo'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if ($logo)
            <img src="{{ $logo }}" alt="Logo" style="max-width:200px; height:auto;">
            @elseif (trim($slot) === 'Laravel')
            <img src="{{ asset('/storage/images/cdg.png') }}" alt="Logo" style="max-width:200px; height:auto;">
            @else
            {!! $slot !!}
            @endif
        </a>
    </td>
</tr>