
@include('partials.header')

<h1
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 26px !important;line-height: 1.3 !important;color: #181818 !important;margin: 0 0 20px 0 !important;">
    New Invitation</h1>

<h2
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 20px !important;line-height: 1.4 !important;color: #181818 !important;margin: 0 0 20px 0 !important;">
    {{$sender}} invited you to join {{$companyName}}</h2>

<p
    style="font-family: Arial, Helvetica, sans-serif !important;font-size: 14px !important;line-height: 1.5 !important;color: #181818 !important;margin: 0 0 13px 0 !important;">
    If you think this may be an error, please ignore this message. Otherwise, accept this invitation here:</p>

<p>
    <a href={{$invitationUrl}} target="_blank"
        style="display: inline-block !important;font-family: Arial, Helvetica, sans-serif !important;font-size: 18px !important;font-weight: bold !important;color: #ffffff !important;text-decoration: none !important;padding: 10px 20px !important;border-radius: 5px !important;background-color: #6100d3 !important;margin-top: 10px !important;margin-bottom: 10px !important;">Accept
        Invitation</a>
</p>

@include('partials.footer')
