<!DOCTYPE html>
<html>
<head>
    <title>Social Auth Callback</title>
</head>
<body>
    <script>
        // Send the authorization code to the parent window
        if (window.opener) {
            window.opener.postMessage({
                type: 'SOCIAL_AUTH_SUCCESS',
                code: '{{ $code }}',
                state: '{{ $state }}'
            }, '*');
            window.close();
        } else {
            // Fallback if popup fails
            document.body.innerHTML = '<p>Autenticación completada. Puedes cerrar esta ventana.</p>';
            setTimeout(() => {
                window.close();
            }, 2000);
        }
    </script>
    <p>Procesando autenticación...</p>
</body>
</html>
