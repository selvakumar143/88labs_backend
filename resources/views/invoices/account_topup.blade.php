<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice['invoice_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .header { margin-bottom: 16px; }
        .title { font-size: 22px; font-weight: 700; margin: 0; }
        .meta { width: 100%; margin-top: 12px; border-collapse: collapse; }
        .meta td { padding: 4px 8px; border: 1px solid #e5e7eb; }
        .section { margin-top: 16px; }
        .section h3 { font-size: 14px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Ad Account Topup Invoice</p>
        <div>Invoice No: {{ $invoice['invoice_number'] }}</div>
        <div>Reference: {{ $invoice['reference'] }}</div>
    </div>

    <table class="meta">
        <tr><td>Client</td><td>{{ $invoice['client']['name'] ?? '-' }}</td><td>Status</td><td>{{ strtoupper((string) ($invoice['status'] ?? '-')) }}</td></tr>
        <tr><td>Client Email</td><td>{{ $invoice['client']['email'] ?? '-' }}</td><td>Currency</td><td>{{ $invoice['currency'] ?? '-' }}</td></tr>
        <tr><td>Business Name</td><td>{{ $invoice['details']['business_name'] ?? '-' }}</td><td>Business Manager</td><td>{{ $invoice['details']['business_manager_name'] ?? '-' }}</td></tr>
        <tr><td>Account ID</td><td>{{ $invoice['details']['account_id'] ?? '-' }}</td><td>Account Name</td><td>{{ $invoice['details']['account_name'] ?? '-' }}</td></tr>
        <tr><td>Created At</td><td>{{ $invoice['created_at'] ?? '-' }}</td><td>Approved At</td><td>{{ $invoice['approved_at'] ?? '-' }}</td></tr>
    </table>

    <div class="section">
        <h3>Items</h3>
        <table>
            <thead>
                <tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
            </thead>
            <tbody>
                @foreach($invoice['line_items'] as $line)
                    <tr>
                        <td>{{ $line['description'] }}</td>
                        <td>{{ $line['quantity'] }}</td>
                        <td>{{ number_format((float) $line['unit_price'], 2) }}</td>
                        <td>{{ number_format((float) $line['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section right">
        <div>Subtotal: {{ number_format((float) $invoice['subtotal'], 2) }} {{ $invoice['currency'] ?? '' }}</div>
        <div><strong>Total: {{ number_format((float) $invoice['total'], 2) }} {{ $invoice['currency'] ?? '' }}</strong></div>
    </div>
</body>
</html>
