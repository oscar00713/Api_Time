@include('partials.header')

<h1
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 26px !important;line-height: 1.3 !important;color: #181818 !important;margin: 0 0 20px 0 !important;">
    Welcome!</h1>

<h2
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 20px !important;line-height: 1.4 !important;color: #181818 !important;margin: 0 0 20px 0 !important;">
    Confirm your Account</h2>

<p
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 14px !important;line-height: 1.5 !important;color: #181818 !important;margin: 0 0 13px 0 !important;">
    Please, confirm your account with the following PIN code:</p>

<div
    style="display: inline-block;font-family: 'Courier New', Courier, monospace;font-size: 28px;font-weight: bold;color: #6100d3;background-color: #f4f4f4;border: 2px dashed #6100d3;padding: 15px 25px;border-radius: 8px;letter-spacing: 5px;margin-top: 15px;margin-bottom: 15px;">
    {{$pin}}</div>

<p
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 14px !important;line-height: 1.5 !important;color: #181818 !important;margin: 0 0 13px 0 !important;">
    If you have any problems, please contact our Support Center.</p>

<p
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 14px !important;line-height: 1.5 !important;color: #181818 !important;margin: 0 0 13px 0 !important;">
    The Timeboard.live team</p>

@include('partials.footer')
