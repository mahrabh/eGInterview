<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNo }}</title>
    <style>
        @page { margin: 0.75in; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #1e293b; line-height: 1.5; }
        .header { border-bottom: 3px solid #e2e8f0; padding-bottom: 18px; margin-bottom: 28px; }
        .brand { font-size: 24px; font-weight: 900; color: #4f46e5; letter-spacing: -0.5px; }
        .brand span { color: #0ea5e9; }
        .muted { color: #64748b; }
        .box-title { font-size: 10px; font-weight: 900; text-transform: uppercase; color: #4f46e5; letter-spacing: 0.08em; margin-bottom: 8px; border-bottom: 2px solid #f1f5f9; padding-bottom: 4px; }
        table.meta { width: 100%; margin-bottom: 24px; }
        table.meta td { vertical-align: top; width: 50%; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #f8fafc; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; padding: 12px 10px; border-bottom: 3px solid #4f46e5; }
        table.items td { padding: 14px 10px; border-bottom: 1px solid #f1f5f9; }
        .total { margin-top: 24px; float: right; width: 240px; }
        .total .row { display: block; padding: 6px 0; }
        .grand { font-size: 18px; font-weight: 900; border-top: 3px solid #4f46e5; padding-top: 10px; margin-top: 8px; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: 5px 12px; border-radius: 6px; font-size: 10px; font-weight: 900; text-transform: uppercase; }
        .footer { position: fixed; bottom: 0.4in; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td>
                    <div class="brand">eg<span>Meet</span> AI</div>
                    <div class="muted" style="margin-top:6px;">{{ $company['email'] }}</div>
                </td>
                <td style="text-align:right;">
                    <div style="font-size:22px;font-weight:900;">INVOICE</div>
                    <div class="muted" style="margin-top:6px;">{{ $invoiceNo }}</div>
                    <div style="margin-top:10px;"><span class="badge">{{ $payment->statusLabel() }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td>
                <div class="box-title">Bill To</div>
                <div style="font-weight:700;">{{ $payment->user?->name }}</div>
                <div class="muted">{{ $payment->user?->email }}</div>
            </td>
            <td style="text-align:right;">
                <div class="box-title">Details</div>
                <div><strong>Issued:</strong> {{ $payment->created_at->format('M j, Y') }}</div>
                <div><strong>Paid:</strong> {{ $payment->paid_at?->format('M j, Y') ?? '—' }}</div>
                <div><strong>Gateway:</strong> {{ $payment->gateway ?? '—' }}</div>
                <div><strong>Period:</strong> {{ $payment->periodLabel() }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Plan</th>
                <th>Module</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div style="font-weight:700;">{{ $payment->description }}</div>
                    @if($payment->recruitment_limit !== null || $payment->loan_limit !== null)
                        <div class="muted" style="font-size:11px;margin-top:4px;">
                            @if($payment->recruitment_limit !== null) Recruitment limit: {{ $payment->recruitment_limit }} @endif
                            @if($payment->recruitment_limit !== null && $payment->loan_limit !== null) · @endif
                            @if($payment->loan_limit !== null) Loan Interview Limit: {{ $payment->loan_limit }} @endif
                        </div>
                    @endif
                </td>
                <td>{{ $payment->plan_name ?? '—' }}</td>
                <td>{{ $payment->plan_module ?? '—' }}</td>
                <td style="text-align:right;font-weight:700;">${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total">
        <div class="grand">Total: ${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</div>
    </div>

    @if($payment->notes)
        <div style="clear:both;margin-top:48px;">
            <div class="box-title">Notes</div>
            <div class="muted">{{ $payment->notes }}</div>
        </div>
    @endif

    <div class="footer">{{ $company['name'] }} · Invoice {{ $invoiceNo }}</div>
</body>
</html>
