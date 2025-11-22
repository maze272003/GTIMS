<!DOCTYPE html>
<html>
<head>
    <title>Patient Records Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        .header p { margin: 5px 0; color: #555; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; font-weight: bold; }
        
        .badge { padding: 2px 5px; background: #eee; border-radius: 3px; font-size: 10px; }
        .meta { margin-bottom: 15px; font-size: 11px; color: #333; }
        
        /* Medication List styling inside table */
        .med-list { margin: 0; padding-left: 15px; }
        .med-list li { margin-bottom: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>Patient Dispensation Records</h2>
        <p>Generated on: {{ $date }} | By: {{ $generated_by }}</p>
    </div>

    <div class="meta">
        <strong>Filters Applied:</strong> 
        Date: {{ $filters['from'] ? $filters['from'] : 'Start' }} to {{ $filters['to'] ? $filters['to'] : 'Current' }}
        @if($filters['category']) | Category: {{ $filters['category'] }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 20%">Patient Details</th>
                <th style="width: 15%">Location</th>
                <th style="width: 10%">Category</th>
                <th style="width: 35%">Medications Dispensed</th>
                <th style="width: 15%">Date Dispensed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($patientrecords as $record)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <strong>{{ $record->patient_name }}</strong><br>
                        <span style="color: #666; font-size: 10px;">ID: {{ $record->id }}</span>
                    </td>
                    <td>
                        {{ $record->barangay->barangay_name ?? 'N/A' }}<br>
                        <small>Purok: {{ $record->purok }}</small>
                    </td>
                    <td>{{ $record->category }}</td>
                    <td>
                        @if($record->dispensedMedications->count() > 0)
                            <ul class="med-list">
                                @foreach($record->dispensedMedications as $med)
                                    <li>
                                        {{ $med->generic_name }} ({{ $med->quantity }})
                                        <br><small style="color:#666">{{ $med->strength }} - {{ $med->brand_name }}</small>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <em>No medications</em>
                        @endif
                    </td>
                    <td>
                        {{ $record->date_dispensed->format('M d, Y') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No records found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>