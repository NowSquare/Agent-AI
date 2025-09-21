<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Agent AI - Verify Code</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h1 class="text-center text-3xl font-extrabold text-gray-900">Agent AI</h1>
                <h2 class="mt-6 text-center text-2xl font-bold text-gray-900">
                    Enter your login code
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    We sent a 6-digit code to your email
                </p>
            </div>

            <form id="verifyForm" class="mt-8 space-y-6">
                @csrf
                <input type="hidden" id="challengeId" name="challenge_id" value="{{ request('challenge_id') }}">

                <div>
                    <label for="code" class="sr-only">Verification Code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        maxlength="6"
                        pattern="\d{6}"
                        required
                        class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 text-center text-2xl tracking-widest focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10"
                        placeholder="000000"
                    >
                </div>

                <div>
                    <button
                        id="submitBtn"
                        type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        <span id="btnText">Verify Code</span>
                    </button>
                </div>

                <div class="text-center">
                    <button
                        type="button"
                        onclick="window.location.href='/auth/challenge'"
                        class="text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        Didn't receive code? Try again
                    </button>
                </div>

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-4">
                        <div class="text-sm text-red-700">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <script>
        // Auto-focus the code input
        document.getElementById('code').focus();

        document.getElementById('verifyForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const challengeId = document.getElementById('challengeId').value;
            const code = document.getElementById('code').value;

            if (!challengeId) {
                alert('Missing challenge ID. Please go back and try again.');
                return;
            }

            // Disable button and show loading
            submitBtn.disabled = true;
            btnText.textContent = 'Verifying...';

            try {
                const response = await fetch('/auth/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        challenge_id: challengeId,
                        code: code
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    // Redirect to dashboard
                    window.location.href = '/dashboard';
                } else {
                    throw new Error(data.message || 'Invalid code');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Verify Code';
            }
        });
    </script>
</body>
</html>
