<div class="am-training-dates {{ ($compact ?? false) ? 'am-training-dates--compact' : '' }}" aria-label="Training date range">
    <div><span>From</span><strong>{{ $training->date_conducted?->format('M d, Y') ?? 'Not scheduled' }}</strong></div>
    <span class="am-training-dates__arrow" aria-hidden="true">→</span>
    <div><span>To</span><strong>{{ $training->end_date?->format('M d, Y') ?? 'Not provided' }}</strong></div>
</div>
