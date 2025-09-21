<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Agent AI - Sign In</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h1 class="text-center text-3xl font-extrabold text-gray-900">Agent AI</h1>
                <h2 class="mt-6 text-center text-2xl font-bold text-gray-900">
                    Sign in to your account
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    We'll send you a login code via email
                </p>
            </div>

            <form id="challengeForm" class="mt-8 space-y-6">
                @csrf
                <div>
                    <label for="identifier" class="sr-only">Email address</label>
                    <input
                        id="identifier"
                        name="identifier"
                        type="email"
                        required
                        class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                        placeholder="Email address"
                    >
                </div>

                <div>
                    <button
                        id="submitBtn"
                        type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        <span id="btnText">Send Login Code</span>
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

            <!-- Success Message -->
            <div id="successMessage" class="hidden rounded-md bg-green-50 p-4">
                <div class="text-sm text-green-700">
                    Check your email for a login code!
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('challengeForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const identifier = document.getElementById('identifier').value;
            const successMessage = document.getElementById('successMessage');

            // Disable button and show loading
            submitBtn.disabled = true;
            btnText.textContent = 'Sending...';

            try {
                const response = await fetch('/auth/challenge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        identifier: identifier
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    successMessage.classList.remove('hidden');
                    // Optionally redirect to verify page with challenge_id
                    setTimeout(() => {
                        window.location.href = `/auth/verify?challenge_id=${data.challenge_id}`;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Something went wrong');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Send Login Code';
            }
        });
    </script>
</body>
</html>
