<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Preview transfer complete</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 2rem; }
        .card { max-width: 48rem; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem 2rem; }
        h1 { font-size: 1.25rem; }
        p { color: #475569; }
        pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: 0.375rem; overflow-x: auto; font-size: 0.8rem; }
        a { color: #b45309; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Preview transfer complete</h1>
        <p>
            This is a storefront preview, not a real Coupa session, so nothing was actually sent anywhere.
            Below is the exact cXML <code>PunchOutOrderMessage</code> a real transfer would have posted to Coupa.
        </p>
        <pre>{{ $cxml }}</pre>
        <p><a href="{{ url('/admin') }}">Back to Admin</a></p>
    </div>
</body>
</html>
