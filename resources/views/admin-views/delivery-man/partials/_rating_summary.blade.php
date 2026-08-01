<section class="driver-rating-summary w-100" data-testid="driver-rating-summary"
    aria-label="{{ $reviewLabel }}">
    <div class="d-flex flex-column flex-sm-row align-items-stretch gap-3 w-100">
        <div class="driver-rating-overview d-flex flex-column align-items-center justify-content-center px-sm-3">
            <img width="75" class="mb-2"
                src="{{ asset((int) data_get($ratingSummary, 'total', 0) > 0
                    ? 'public/assets/admin/img/icons/rating-stars.png'
                    : 'public/assets/admin/img/icons/no_rating.png') }}"
                alt="">

            <div class="rating--review text-center">
                <h3 class="title mb-0" data-testid="driver-average-rating">
                    {{ number_format((float) data_get($ratingSummary, 'average', 0), 1) }}<span class="out-of">/5</span>
                </h3>
                <div class="info" data-testid="driver-review-count">
                    {{ (int) data_get($ratingSummary, 'total', 0) }} {{ $reviewLabel }}
                </div>
            </div>

            @if ((int) data_get($ratingSummary, 'total', 0) === 0)
                <p class="driver-rating-empty mb-0 mt-2 text-center" data-testid="driver-rating-empty">
                    {{ $emptyRatingMessage }}
                </p>
            @endif
        </div>

        <ul class="driver-rating-distribution list-unstyled list-unstyled-py-2 mb-0 py-2 flex-grow-1 review-color-progress"
            data-testid="driver-rating-distribution">
            @foreach ([5, 4, 3, 2, 1] as $star)
                <li class="d-flex align-items-center font-size-sm" data-rating="{{ $star }}">
                    <span class="progress-name mr-0">{{ $ratingLabels[$star] }}</span>
                    <div class="progress flex-grow-1" aria-hidden="true">
                        <div class="progress-bar" role="progressbar"
                            style="width: {{ (float) data_get($ratingSummary, "distribution.$star.percentage", 0) }}%;"
                            aria-valuenow="{{ (float) data_get($ratingSummary, "distribution.$star.percentage", 0) }}"
                            aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span class="driver-rating-count text-right">
                        {{ (int) data_get($ratingSummary, "distribution.$star.count", 0) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
