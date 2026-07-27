@php
  $band = str_repeat('Al Seef &nbsp;&nbsp;&middot;&nbsp;&nbsp;', 12);
@endphp
<table cellpadding="8" cellspacing="0" width="100%">
  <tr>
    <td bgcolor="#fffdf5" style="border:1px solid #e8d5a3;">
      <table cellpadding="0" cellspacing="0" width="100%">
        <tr>
          <td width="50%" style="font-family:helvetica;vertical-align:middle;">
            <table cellpadding="0" cellspacing="0">
              <tr>
                @if($managementLogoPath)
                <td style="vertical-align:middle;padding-right:8pt;">
                  <img src="{{ $managementLogoPath }}" height="20mm"/>
                </td>
                @endif
                <td style="vertical-align:middle;">
                  <span style="font-size:18pt;font-weight:bold;color:#4a3000;">Al Seef</span><br/>
                  <span style="font-size:7pt;color:#8B6914;letter-spacing:2px;">LUXURY WATERFRONT LIVING</span>
                </td>
              </tr>
            </table>
          </td>
          <td width="50%" style="text-align:right;font-family:helvetica;vertical-align:middle;">
            <span style="font-size:14pt;font-weight:bold;color:#222222;">Booking <span style="color:#cc0000;">Confirmation</span></span><br/>
            <span style="font-size:7pt;color:#666666;">Please present either an electronic or paper copy of this confirmation upon check-in.</span>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td bgcolor="#C9A96E" style="color:#ffffff;font-size:6.5pt;font-weight:bold;font-family:helvetica;">{!! $band !!}</td>
  </tr>
</table>

<table cellpadding="12" cellspacing="0" width="100%">
  <tr>
    <td width="60%" style="border:1px solid #dddddd;font-family:helvetica;font-size:15pt;line-height:1.7;vertical-align:top;">
      <span style="font-size:16pt;font-weight:bold;color:#4a3000;">Guest &amp; Booking Information</span><br/>
      <span style="color:#555555;">Booking ID:</span> <span style="font-weight:bold;">{{ $booking->id }}</span><br/>
      <span style="color:#555555;">Client:</span> <span style="font-family:amiri;font-weight:bold;font-size:18pt;">{{ $booking->guest->name ?? '—' }}</span><br/>
      @if($booking->guest->id_number ?? null)
        <span style="color:#555555;">Civil / Passport ID:</span> <span style="font-weight:bold;">{{ $booking->guest->id_number }}</span><br/>
      @endif
      @if($booking->guest->nationality ?? null)
        <span style="color:#555555;">Nationality:</span> <span style="font-family:amiri;font-weight:bold;">{{ $booking->guest->nationality }}</span><br/>
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
    <td width="40%" style="border:1px solid #dddddd;font-family:helvetica;font-size:15pt;line-height:1.7;vertical-align:top;">
      <span style="font-size:16pt;font-weight:bold;color:#4a3000;">Stay Details</span><br/>
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

<table cellpadding="2" cellspacing="0" width="100%">
  <tr>
    <td bgcolor="#fafafa" style="border:1px solid #dddddd;font-family:helvetica;font-size:11.5pt;">
      <b>Booking Status:</b> {{ strtoupper($booking->status ?? '') }}
      @if($booking->notes)
        &nbsp;&nbsp;<b>Notes:</b> <span style="font-family:amiri;">{{ $booking->notes }}</span>
      @endif
    </td>
  </tr>
</table>

<table cellpadding="2" cellspacing="0" width="100%">
  <tr>
    <td width="50%" style="font-family:helvetica;font-size:11pt;color:#666666;">
      ARRIVAL&nbsp; <span style="font-size:17pt;font-weight:bold;color:#111111;border:1px solid #aaaaaa;">&nbsp;{{ $booking->check_in->format('j M Y') }}@if($booking->check_in_time) &nbsp;@&nbsp;{{ $booking->check_in_time }}@endif&nbsp;</span>
    </td>
    <td width="50%" style="font-family:helvetica;font-size:11pt;color:#666666;">
      DEPARTURE&nbsp; <span style="font-size:17pt;font-weight:bold;color:#111111;border:1px solid #aaaaaa;">&nbsp;{{ $booking->check_out->format('j M Y') }}&nbsp;</span>
    </td>
  </tr>
</table>

<hr style="color:#C9A96E;height:1pt;margin:1pt 0;"/>

<!--RTL_START-->
<div style="font-family:amiri;font-size:8pt;line-height:1.3;color:#000000;text-align:right;" dir="rtl">
  <p style="margin:0 0 2pt;text-align:center;font-size:10pt;font-weight:bold;color:#000000;">الأحكام والشروط</p>

  <p style="margin:0.5pt 0;"><b>وقت الدخول:</b> وقت دخول الفيلا ابتداء من الساعة <span dir="ltr"><b>2:00</b> إلى <b>3:00</b></span> ظهراً.</p>
  <p style="margin:0.5pt 0;"><b>مبلغ التأمين:</b> يتم دفع تأمين مسترد قبل الدخول للفيلا وقدره <b><span dir="ltr">50</span> ريال عماني</b>.</p>
  <p style="margin:0.5pt 0;"><b>نظافة الفيلا:</b> يجب تسليم الفيلا عند الخروج نظيفة كما تم استلامها، حيث إن عدم الالتزام بالنظافة العامة سيؤدي إلى خصم مبلغ من التأمين.</p>
  <p style="margin:0.5pt 0;"><b>رمال الشاطئ:</b> حرصاً على نظافة الفيلا وراحتكم، يُرجى التكرم بغسل الأرجل وإزالة رمال الشاطئ تماماً قبل الدخول.</p>
  <p style="margin:0.5pt 0;"><b>الأثاث:</b> يمنع منعاً باتاً تحريك أو نقل الأثاث من مكانه المخصص.</p>
  <p style="margin:0.5pt 0;"><b>النفايات:</b> يرجى وضع النفايات في الأكياس المخصصة لها، ويمكنكم طلب أكياس إضافية من مكتب الإدارة عند الحاجة.</p>
  <p style="margin:0.5pt 0;"><b>الملابس المبللة:</b> يرجى تجنب الجلوس بملابس السباحة المبللة على أثاث الفيلا الداخلي بعد العودة من الشاطئ.</p>
  <p style="margin:0.5pt 0;"><b>إخلاء مسؤولية:</b> تخلي إدارة مجمع فلل السيف السكنية مسؤوليتها القانونية والتامة عن أي حوادث، إصابات شخصية في الفيلا او المجمع السكني.</p>
  <p style="margin:0.5pt 0;"><b>وقت المغادرة:</b> يجب الالتزام بتسجيل الخروج في تمام الساعة <b><span dir="ltr">11:00</span> ظهراً</b> كحد أقصى تجنباً لخصم مبلغ التأمين.</p>
  <p style="margin:0.5pt 0;background-color:#fffbe6;"><b>الإقرار والموافقة القانونية:</b> إن إتمامكم لعملية دفع المبالغ المستحقة سواء العربون أو كامل المبلغ وتأكيد الحجز، يُعد بمثابة توقيع إلكتروني، وموافقة نهائية منكم بالالتزام بكافة الشروط، والأحكام المذكورة أعلاه.</p>

  @if($receptionPhone1 || $receptionPhone2)
  <p style="margin:0.5pt 0;">يرجى التواصل على الأرقام التالية قبل الوصول بساعة: <span dir="ltr">{{ implode(' / ', array_filter([$receptionPhone1, $receptionPhone2])) }}</span></p>
  @endif

  <p style="margin:1.5pt 0 0;text-align:center;font-weight:bold;color:#000000;">نتمنى لكم إقامة مريحة ورحلة سعيدة في فلل السيف</p>
</div>
<!--RTL_END-->

<table cellpadding="0" cellspacing="0" width="100%">
  <tr>
    <td width="77%" style="vertical-align:top;padding-right:4pt;">
      <table cellpadding="8" cellspacing="0" width="100%">
        <tr>
          <td bgcolor="#fffdf5" style="border:1px solid #e8d5a3;font-family:helvetica;font-size:8pt;line-height:1.5;">
            <span style="font-size:9pt;font-weight:bold;color:#4a3000;">Al Seef &mdash; Luxury Waterfront Living</span><br/>
            Muscat, Sultanate of Oman<br/>
            @if($receptionPhone1 || $receptionPhone2)
              Reception: {{ implode(' / ', array_filter([$receptionPhone1, $receptionPhone2]) ) }}<br/>
            @endif
            <span style="color:#999999;font-size:6.5pt;">Generated: {{ $generatedAt }}</span>
          </td>
        </tr>
      </table>
    </td>
    <!-- Fixed-width, no-slack column: its right edge IS the page's right margin, so the
         stamp box (set to fill 100% of it, not a fixed mm size) sits flush at the true
         edge instead of relying on TCPDF's unreliable table align="right" support. -->
    <td width="23%" style="vertical-align:top;">
      <table cellpadding="3" cellspacing="0" width="100%">
        <tr>
          <td bgcolor="#ffffff" style="border:1px solid #C9A96E;text-align:center;">
            @if($stampImagePath)
              <img src="{{ $stampImagePath }}" width="22mm" height="16mm"/>
            @else
              <span style="color:#8B6914;font-weight:bold;">Authorized<br/>Stamp &amp; Signature</span>
            @endif
          </td>
        </tr>
      </table>
      <div style="font-family:helvetica;font-size:5.5pt;color:#999999;margin-top:2pt;text-align:center;">Official Seal</div>
    </td>
  </tr>
</table>
