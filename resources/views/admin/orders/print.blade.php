<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Replenishment Order #{{ $order->id }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding: 50px 60px;
            background: #fff;
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
        }

        /* Header - Pure Black & White, Official Look */
        .header {
            text-align: center;
            margin-bottom: 45px;
            padding-bottom: 18px;
            border-bottom: 3px double #000;
        }

        .header h1 {
            margin: 0 0 10px 0;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .header .subtitle {
            font-size: 12pt;
            font-weight: bold;
            margin: 8px 0;
        }

        .header .generated {
            font-size: 11pt;
            margin-top: 14px;
            font-weight: normal;
        }

        /* Meta Info - Clean Two Columns */
        .meta-info {
            margin: 40px 0;
            font-size: 11pt;
            text-align: left;
        }

        .meta-info table {
            width: 100%;
            border-collapse: collapse;

        }

        .meta-info td {
            padding: 7px 0;
            vertical-align: top;
            text-align: left;
        }

        .meta-info .label {
            font-weight: bold;
            width: fit;
            display: inline-block;
        }

        /* Items Table - Clean Borders, No Fill Colors */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 50px 0;
            font-size: 11pt;
            border: 2px solid #000;
        }

        table.items th {
            padding: 14px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border: 1px solid #000;
        }

        table.items td {
            padding: 12px 10px;
            border: 1px solid #000;
        }

        table.items td:first-child {
            border-left: none;
        }

        table.items tr:last-child td {
            border-bottom: none;
        }

        table.items .generic {
            font-weight: bold;
        }

        table.items .qty {
            text-align: center;
            font-weight: bold;
            font-size: 12.5pt;
        }

        /* Remarks */
        .remarks {
            margin: 50px 0;
            padding: 18px;
            border: 1px dashed #333;
            background: #f9f9f9;
            font-style: italic;
        }

        .remarks strong {
            font-style: normal;
            font-weight: bold;
        }

        /* Approval Section - Classic Government Style */
        .approval-section {
            margin-top: 80px;
            padding-top: 35px;
            border-top: 3px double #000;
        }

        .approval-title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 50px;
        }

        .approval-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 30px;
        }

        .approval-block {
            flex: 1;
            text-align: center;
        }

        .signature-line {
            border-top: 2px solid #000;
            width: 80%;
            margin: 55px auto 12px;
        }

        .approval-label {
            font-weight: bold;
            font-size: 12pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .approval-name {
            font-weight: bold;
            font-size: 12pt;
            margin: 10px 0 5px;
        }

        .approval-role {
            font-style: italic;
            font-size: 11pt;
            color: #333;
        }

        .approval-date {
            margin-top: 8px;
            font-size: 11pt;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            margin-top: 90px;
            padding-top: 25px;
            border-top: 1px solid #000;
            font-size: 10.5pt;
            line-height: 1.7;
            font-style: italic;
        }

        /* Print Button */
        .no-print {
            text-align: right;
            margin-bottom: 30px;
        }

        .no-print button {
            background: #000;
            color: #fff;
            border: none;
            padding: 12px 35px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Force Black & White Print */
        @media print {
            body {
                padding: 40px 50px;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .no-print { display: none !important; }
            @page { margin: 10px; size: A4; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="container">

        <!-- Header -->
        <div class="header">
            <div>
                <img src="{{asset('images/letterhead.png')}}" alt="" style="width:100%; max-width:600px;">
            </div>
            <h1>Stock Requisition Form</h1>
        </div>

        <!-- Meta Information -->
        <div class="meta-info">
            <table>
                <tr>
                    <td><span class="label">Order ID:</span> #{{ $order->id }}</td>
                    <td><span class="label">Requesting Branch:</span> {{ $order->branch->name }}</td>
                </tr>
                <tr>
                    <td><span class="label">Date Created:</span> {{ $order->created_at->format('F d, Y h:i A') }}</td>
                    <td><span class="label">Requested By:</span> {{ $order->user->name }}</td>
                </tr>
                <tr>
                    <td><span class="label">Status:</span> <strong>{{ strtoupper($order->status) }}</strong></td>
                    <td><span class="label">Position:</span> Pharmacist / Branch Personnel</td>
                </tr>
            </table>
        </div>
        <!-- Medicines Table -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width:6%;">#</th>
                    <th style="width:28%;">Generic Name</th>
                    <th style="width:22%;">Brand Name</th>
                    <th style="width:20%;">Source Branch</th>
                    <th style="width:14%;">Batch</th>
                    <th style="width:10%;">Qty Requested</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="generic">{{ $item->product->generic_name }}</td>
                    <td>{{ $item->product->brand_name ?? '-' }}</td>
                    <td>{{ $item->sourceBranch?->name ?? 'N/A' }}</td>
                    <td>{{ $item->source_batch_number ?? 'N/A' }}</td>
                    <td class="qty">{{ number_format($item->quantity_requested) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Remarks -->
        @if($order->remarks)
        <div class="remarks">
            <strong>Notes / Special Instructions:</strong><br><br>
            {{ nl2br(e($order->remarks)) }}
        </div>
        @endif

        <!-- Approval Signatures -->
        <div class="approval-section">
            <div class="approval-title">Approval Signatures</div>

            <div class="approval-row">
                <div class="approval-block">
                    <div class="signature-line"></div>
                    <div class="approval-name">{{ $order->user->name }}</div>
                    <div class="approval-role">Pharmacist / Branch Requestor</div>
                    <div class="approval-date">{{ $order->created_at->format('F d, Y') }}</div>
                </div>
                
                <div class="approval-block">
                    <div class="signature-line"></div>
                    <div class="approval-label">Melanie Q. Ramos</div>
                    <div class="approval-name">
                    </div>
                    <div class="approval-role">Gen. Tinio Office / Treasurer</div>
                    <div class="approval-date">
                        {{ $order->admin_approved_at ? \Carbon\Carbon::parse($order->admin_approved_at)->format('F d, Y') : 'Pending Approval' }}
                    </div>
                </div>

                <div class="approval-block">
                    <div class="signature-line"></div>
                    <div class="approval-label">Sherry Ann D. Bolisay</div>
                    <div class="approval-name">
                    </div>
                    <div class="approval-role">Mun. Mayor / Authorized Official</div>
                    <div class="approval-date">
                        {{ $order->finance_approved_at ? \Carbon\Carbon::parse($order->finance_approved_at)->format('F d, Y') : 'Pending Approval' }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
