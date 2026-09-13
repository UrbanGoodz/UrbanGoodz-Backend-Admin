@foreach ($reviews as $key => $review)
    <tr>
        <td>{{ $key + 1 }}</td>
        <td>
            @if ($review->item)
                <a class="text-body text-capitalize" href="{{ route('admin.item.view', [$review->item_id]) }}">
                    {{ Str::limit($review->item->name, 25, '...') }}
                </a>
            @else
                <span class="text-muted">{{ translate('item_not_found') }}</span>
            @endif
        </td>
        <td>
            @if ($review->customer)
                {{ $review->customer->f_name }} {{ $review->customer->l_name }}
            @else
                <span class="text-muted">{{ translate('customer_not_found') }}</span>
            @endif
        </td>
        <td>
            {{-- A rating is stored 1-5; render it so a scan down the column reads at a glance. --}}
            <span class="text-warning">
                @for ($i = 1; $i <= 5; $i++)
                    <i class="tio-star{{ $i <= (int) $review->rating ? '' : '-outlined' }}"></i>
                @endfor
            </span>
            <span class="ms-1">{{ (int) $review->rating }}</span>
        </td>
        <td>
            @if (!empty($review->comment))
                {{ Str::limit($review->comment, 60, '...') }}
            @else
                <span class="text-muted">{{ translate('no_comment') }}</span>
            @endif
        </td>
        <td>{{ $review->created_at ? $review->created_at->format('d M Y') : '-' }}</td>
    </tr>
@endforeach
