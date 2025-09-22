{{-- Options Email Template --}}
Hello,

We're not completely sure what you meant by your request. Here are some options to help clarify:

@foreach($options as $option)
[{{ $option['label'] }}]({{ $option['url'] }})
@endforeach

Or reply to this email to clarify your request further: {{ $replyUrl }}

---
This is an automated message from Agent AI.
Choose an option above or reply to provide more details.
