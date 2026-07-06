<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Booking Confirmation #{{ $booking->id }}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:DejaVu Sans, Arial, sans-serif;font-size:13px;color:#222;background:#fff}
  .page{width:760px;margin:0 auto;border:1px solid #bbb}
  /* header */
  .hdr{display:flex;justify-content:space-between;align-items:flex-start;padding:18px 22px 12px}
  .logo{font-family:Georgia,serif;font-size:30px;font-weight:700;letter-spacing:0.18em;color:#4a3000}
  .logo-sub{font-size:10px;letter-spacing:0.22em;color:#8B6914;margin-top:2px}
  .logo-dots{display:flex;gap:5px;margin-top:6px}
  .logo-dot{width:12px;height:12px;border-radius:50%}
  .ttl{text-align:right}
  .ttl h1{font-size:24px;font-weight:700;color:#222}
  .ttl h1 span{color:#c00}
  .ttl p{font-size:10.5px;color:#666;max-width:300px;margin-top:4px;text-align:right}
  /* band */
  .band{background:#C9A96E;padding:5px 12px;font-size:11px;color:#fff;font-weight:600;letter-spacing:0.06em;white-space:nowrap;overflow:hidden}
  /* two-col */
  .body{display:flex;border-bottom:1px solid #ddd}
  .lcol{flex:1;padding:14px 18px;border-right:1px solid #ddd}
  .rcol{width:250px;padding:14px 18px}
  .f{display:flex;margin-bottom:9px;align-items:baseline}
  .fl{width:148px;color:#555;flex-shrink:0;font-size:12px}
  .fv{font-weight:600}
  .fvbox{border:1px solid #bbb;padding:2px 10px;text-align:center;min-width:60px;display:inline-block}
  /* policy */
  .policy{padding:8px 18px;background:#fafafa;border-bottom:1px solid #ddd;font-size:11.5px}
  /* dates */
  .dates{display:flex;gap:36px;padding:14px 18px;border-bottom:1px solid #ddd;align-items:flex-end}
  .db label{font-size:10px;text-transform:uppercase;letter-spacing:0.07em;color:#666;display:block;margin-bottom:4px}
  .db .dv{font-size:16px;font-weight:700;border:1px solid #aaa;padding:5px 18px;display:inline-block}
  /* payments table */
  .ptable{width:100%;border-collapse:collapse;font-size:12px;margin-top:8px}
  .ptable th{background:#f5f5f5;padding:5px 8px;border:1px solid #ddd;text-align:left;font-weight:600}
  .ptable td{padding:5px 8px;border:1px solid #ddd}
  /* booked-by */
  .bb{display:flex;justify-content:space-between;align-items:flex-start;padding:14px 18px;border-bottom:1px solid #ddd}
  .stamp{width:88px;height:64px;border:1px solid #bbb;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:10px;text-align:center;line-height:1.5}
  /* footer */
  .fnote{padding:10px 18px;background:#fffbe6;border-top:1px solid #ffe58f;font-size:11px;color:#7c5c00}
  .section-title{font-weight:700;font-size:12px;margin-bottom:10px;color:#4a3000;border-bottom:1px solid #e8d5a3;padding-bottom:4px}
  .green{color:#389e0d}.orange{color:#d46b08}.red{color:#cf1322}
</style>
</head>
<body>
<div class="page">
  <div class="hdr">
    <div>
      <div class="logo">Al Seef</div>
      <div class="logo-sub">LUXURY WATERFRONT LIVING</div>
      <div class="logo-dots">
        <div class="logo-dot" style="background:#C9A96E"></div>
        <div class="logo-dot" style="background:#8B6914"></div>
        <div class="logo-dot" style="background:#D4B896"></div>
        <div class="logo-dot" style="background:#A0784A"></div>
        <div class="logo-dot" style="background:#C9A96E"></div>
      </div>
    </div>
    <div class="ttl">
      <h1>Booking <span>Confirmation</span></h1>
      <p>Please present either an electronic or paper copy of this confirmation upon check-in.</p>
    </div>
  </div>

  <div class="band">{{ str_repeat('Al Seef &nbsp;&nbsp;·&nbsp;&nbsp;', 10) }}</div>

  <div class="body">
    <div class="lcol">
      <div class="section-title">Guest &amp; Booking Information</div>
      <div class="f"><span class="fl">Booking ID :</span><span class="fv">{{ $booking->id }}</span></div>
      <div class="f"><span class="fl">Client :</span><span class="fv" style="font-size:14px">{{ $booking->guest->name ?? '—' }}</span></div>
      @if($booking->guest->id_number ?? null)
        <div class="f"><span class="fl">Civil / Passport ID :</span><span class="fv">{{ $booking->guest->id_number }}</span></div>
      @endif
      @if($booking->guest->nationality ?? null)
        <div class="f"><span class="fl">Nationality :</span><span class="fv">{{ $booking->guest->nationality }}</span></div>
      @endif
      @if($booking->guest->phone ?? null)
        <div class="f"><span class="fl">Phone :</span><span class="fv">{{ $booking->guest->phone }}</span></div>
      @endif
      <div class="f" style="margin-top:10px"><span class="fl">Property :</span><span class="fv">{{ $booking->villa->name ?? '—' }}</span></div>
      @if($booking->villa->category ?? null)
        <div class="f"><span class="fl">Villa Type :</span><span class="fv">{{ $booking->villa->category }}</span></div>
      @endif
      @if($booking->check_in_time)
        <div class="f"><span class="fl">Check-in Time :</span><span class="fv">{{ $booking->check_in_time }}</span></div>
      @endif
    </div>
    <div class="rcol">
      <div class="section-title">Stay Details</div>
      <div class="f"><span class="fl">Number of Guests :</span><span class="fv fvbox">{{ $booking->num_guests ?? 1 }}</span></div>
      @if($booking->villa->num_rooms ?? null)
        <div class="f"><span class="fl">Rooms :</span><span class="fv fvbox">{{ $booking->villa->num_rooms }}</span></div>
      @endif
      <div class="f"><span class="fl">Nights :</span><span class="fv fvbox">{{ $booking->nights }}</span></div>
      <div class="f" style="margin-top:12px"><span class="fl">Total Amount :</span><span class="fv">{{ $omr($booking->total_amount) }}</span></div>
      <div class="f"><span class="fl">Amount Paid :</span><span class="fv green">{{ $omr($booking->paid_amount) }}</span></div>
      <div class="f"><span class="fl">Remaining :</span><span class="fv {{ $remaining > 0 ? 'orange' : 'green' }}">{{ $omr($remaining) }}</span></div>
      <div class="f" style="margin-top:8px"><span class="fl">Payment Status :</span><span class="fv">{{ strtoupper($booking->payment_status ?? '') }}</span></div>
    </div>
  </div>

  <div class="policy">
    <strong>Booking Status:</strong> {{ strtoupper($booking->status ?? '') }}
    @if($booking->notes)
      &nbsp;&nbsp;&nbsp;<strong>Notes:</strong> {{ $booking->notes }}
    @endif
  </div>

  <div class="dates">
    <div class="db">
      <label>Arrival</label>
      <div class="dv">{{ $booking->check_in->format('F j, Y') }}@if($booking->check_in_time) &nbsp;@&nbsp; {{ $booking->check_in_time }} @endif</div>
    </div>
    <div class="db">
      <label>Departure</label>
      <div class="dv">{{ $booking->check_out->format('F j, Y') }}</div>
    </div>
  </div>

  @if($booking->payments->isNotEmpty())
  <div style="padding:14px 18px;border-bottom:1px solid #ddd">
    <div class="section-title">Payments Received</div>
    <table class="ptable">
      <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Recorded By</th><th>Notes</th></tr></thead>
      <tbody>
        @foreach($booking->payments as $p)
        <tr>
          <td>{{ $p->payment_date ?? '—' }}</td>
          <td>{{ $omr($p->amount) }}</td>
          <td>{{ $methodLabels[$p->method] ?? $p->method }}</td>
          <td>{{ $p->user->name ?? '—' }}</td>
          <td>{{ $p->notes ?? '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  <div class="bb">
    <div style="font-size:12px;line-height:1.8">
      <strong>Al Seef — Luxury Waterfront Living</strong><br>
      Muscat, Sultanate of Oman<br>
      <span style="color:#888;font-size:11px">Generated: {{ $generatedAt }}</span>
    </div>
    <div class="stamp">Authorized<br>Stamp &amp;<br>Signature</div>
  </div>

  <div class="fnote">
    <strong>Note to guest:</strong> Please present a valid photo ID upon check-in. This confirmation serves as your official booking receipt.
    For inquiries please contact the Al Seef reception.
  </div>
</div>
</body>
</html>
