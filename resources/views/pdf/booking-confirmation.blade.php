@php
  $band = str_repeat('Al Seef &nbsp;&nbsp;&middot;&nbsp;&nbsp;', 12);
@endphp
<table cellpadding="0" cellspacing="0" width="100%">
  <tr>
    <td width="50%" style="font-family:helvetica;">
      <span style="font-size:15pt;font-weight:bold;color:#4a3000;">Al Seef</span><br/>
      <span style="font-size:6pt;color:#8B6914;letter-spacing:2px;">LUXURY WATERFRONT LIVING</span>
    </td>
    <td width="50%" style="text-align:right;font-family:helvetica;">
      <span style="font-size:12pt;font-weight:bold;color:#222222;">Booking <span style="color:#cc0000;">Confirmation</span></span><br/>
      <span style="font-size:6.5pt;color:#666666;">Please present either an electronic or paper copy of this confirmation upon check-in.</span>
    </td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td bgcolor="#C9A96E" style="color:#ffffff;font-size:6.5pt;font-weight:bold;font-family:helvetica;">{!! $band !!}</td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td width="60%" style="border:1px solid #dddddd;font-family:helvetica;font-size:7.5pt;vertical-align:top;">
      <span style="font-size:8pt;font-weight:bold;color:#4a3000;">Guest &amp; Booking Information</span><br/>
      <span style="color:#555555;">Booking ID:</span> <span style="font-weight:bold;">{{ $booking->id }}</span><br/>
      <span style="color:#555555;">Client:</span> <span style="font-weight:bold;font-size:9.5pt;">{{ $booking->guest->name ?? '—' }}</span><br/>
      @if($booking->guest->id_number ?? null)
        <span style="color:#555555;">Civil / Passport ID:</span> <span style="font-weight:bold;">{{ $booking->guest->id_number }}</span><br/>
      @endif
      @if($booking->guest->nationality ?? null)
        <span style="color:#555555;">Nationality:</span> <span style="font-weight:bold;">{{ $booking->guest->nationality }}</span><br/>
      @endif
      @if($booking->guest->phone ?? null)
        <span style="color:#555555;">Phone:</span> <span style="font-weight:bold;">{{ $booking->guest->phone }}</span><br/>
      @endif
      <span style="color:#555555;">Property:</span> <span style="font-weight:bold;">{{ $booking->villa->name ?? '—' }}</span><br/>
      @if($booking->villa->category ?? null)
        <span style="color:#555555;">Villa Type:</span> <span style="font-weight:bold;">{{ $booking->villa->category }}</span><br/>
      @endif
      @if($booking->check_in_time)
        <span style="color:#555555;">Check-in Time:</span> <span style="font-weight:bold;">{{ $booking->check_in_time }}</span>
      @endif
    </td>
    <td width="40%" style="border:1px solid #dddddd;font-family:helvetica;font-size:7.5pt;vertical-align:top;">
      <span style="font-size:8pt;font-weight:bold;color:#4a3000;">Stay Details</span><br/>
      <span style="color:#555555;">Guests:</span> <span style="font-weight:bold;">{{ $booking->num_guests ?? 1 }}</span><br/>
      @if($booking->villa->num_rooms ?? null)
        <span style="color:#555555;">Rooms:</span> <span style="font-weight:bold;">{{ $booking->villa->num_rooms }}</span><br/>
      @endif
      <span style="color:#555555;">Nights:</span> <span style="font-weight:bold;">{{ $booking->nights }}</span><br/>
      <span style="color:#555555;">Total:</span> <span style="font-weight:bold;">{{ $omr($booking->total_amount) }}</span><br/>
      <span style="color:#555555;">Paid:</span> <span style="font-weight:bold;color:#389e0d;">{{ $omr($booking->paid_amount) }}</span><br/>
      <span style="color:#555555;">Remaining:</span> <span style="font-weight:bold;color:{{ $remaining > 0 ? '#d46b08' : '#389e0d' }};">{{ $omr($remaining) }}</span><br/>
      <span style="color:#555555;">Status:</span> <span style="font-weight:bold;">{{ strtoupper($booking->payment_status ?? '') }}</span>
    </td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td bgcolor="#fafafa" style="border:1px solid #dddddd;font-family:helvetica;font-size:6.5pt;">
      <b>Booking Status:</b> {{ strtoupper($booking->status ?? '') }}
      @if($booking->notes)
        &nbsp;&nbsp;<b>Notes:</b> {{ $booking->notes }}
      @endif
    </td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td width="50%" style="font-family:helvetica;font-size:6pt;color:#666666;">
      ARRIVAL&nbsp; <span style="font-size:9pt;font-weight:bold;color:#111111;border:1px solid #aaaaaa;">&nbsp;{{ $booking->check_in->format('j M Y') }}@if($booking->check_in_time) &nbsp;@&nbsp;{{ $booking->check_in_time }}@endif&nbsp;</span>
    </td>
    <td width="50%" style="font-family:helvetica;font-size:6pt;color:#666666;">
      DEPARTURE&nbsp; <span style="font-size:9pt;font-weight:bold;color:#111111;border:1px solid #aaaaaa;">&nbsp;{{ $booking->check_out->format('j M Y') }}&nbsp;</span>
    </td>
  </tr>
</table>

<hr style="color:#C9A96E;height:1pt;margin:2pt 0;"/>

<!--RTL_START-->
<div style="font-family:amiri;font-size:5.8pt;line-height:1.15;color:#444444;text-align:right;" dir="rtl">
  <p style="margin:0 0 3pt;text-align:center;font-size:8.5pt;font-weight:bold;color:#8B6914;">الشروط والأحكام</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">وقت الدخول</p>
  <p style="margin:0 0 1pt;">وقت دخول الفيلا ابتداء من الساعة <b><span dir="ltr">1:00</span></b> إلى <b><span dir="ltr">2:00</span></b> ظهراً.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">مبلغ التأمين</p>
  <p style="margin:0 0 1pt;">يتم دفع تأمين مسترد قبل الدخول للفيلا وقدره <b><span dir="ltr">50</span> ريال عماني</b>.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">نظافة الفيلا</p>
  <p style="margin:0 0 1pt;">يجب تسليم الفيلا عند الخروج نظيفة كما تم استلامها، حيث إن عدم الالتزام بالنظافة العامة سيؤدي إلى خصم مبلغ من التأمين.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">رمال الشاطئ</p>
  <p style="margin:0 0 1pt;">حرصاً على نظافة الفيلا وراحتكم، يُرجى التكرم بغسل الأرجل وإزالة رمال الشاطئ تماماً قبل الدخول.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">الأثاث</p>
  <p style="margin:0 0 1pt;">يمنع منعاً باتاً تحريك أو نقل الأثاث من مكانه المخصص.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">النفايات</p>
  <p style="margin:0 0 1pt;">يرجى وضع النفايات في الأكياس المخصصة لها، ويمكنكم طلب أكياس إضافية من مكتب الإدارة عند الحاجة.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">الملابس المبللة</p>
  <p style="margin:0 0 1pt;">يرجى تجنب الجلوس بملابس السباحة المبللة على أثاث الفيلا الداخلي بعد العودة من الشاطئ.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">إخلاء مسؤولية</p>
  <p style="margin:0 0 1pt;">تخلي إدارة مجمع فلل السيف السكنية مسؤوليتها القانونية والتامة عن أي حوادث، إصابات شخصية في الفيلا او المجمع السكني.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">وقت المغادرة</p>
  <p style="margin:0 0 1pt;">يجب الالتزام بتسجيل الخروج في تمام الساعة <b><span dir="ltr">10:00</span> صباحاً</b> كحد أقصى تجنباً لخصم مبلغ التأمين.</p>

  <p style="margin:2pt 0 0;font-size:6.5pt;font-weight:bold;color:#4a3000;">الإقرار والموافقة القانونية</p>
  <p style="margin:0 0 1pt;background-color:#fffbe6;">إن إتمامكم لعملية دفع المبالغ المستحقة سواء العربون أو كامل المبلغ وتأكيد الحجز، يُعد بمثابة توقيع إلكتروني، وموافقة نهائية منكم بالالتزام بكافة الشروط، والأحكام المذكورة أعلاه.</p>

  <p style="margin:2pt 0 0;">يرجى التواصل على الارقام التالية قبل الوصول بساعة:<br/>
  <span dir="ltr">76767769</span> &nbsp; <span dir="ltr">76767768</span></p>

  <p style="margin:2pt 0 0;text-align:center;font-weight:bold;color:#8B6914;">نتمنى لكم إقامة مريحة ورحلة سعيدة في فلل السيف</p>
</div>
<!--RTL_END-->

<table cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td width="70%" style="font-family:helvetica;font-size:7pt;line-height:1.4;">
      <b>Al Seef &mdash; Luxury Waterfront Living</b><br/>
      Muscat, Sultanate of Oman<br/>
      @if($receptionPhone1 || $receptionPhone2)
        Reception: {{ implode(' / ', array_filter([$receptionPhone1, $receptionPhone2]) ) }}<br/>
      @endif
      <span style="color:#888888;font-size:6pt;">Generated: {{ $generatedAt }}</span>
    </td>
    <td width="30%" style="font-family:helvetica;font-size:6pt;color:#aaaaaa;text-align:center;border:1px solid #bbbbbb;">
      @if($stampImagePath)
        <img src="{{ $stampImagePath }}" style="max-width:70pt;max-height:35pt;"/>
      @else
        Authorized<br/>Stamp &amp; Signature
      @endif
    </td>
  </tr>
</table>
