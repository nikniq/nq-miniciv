{{-- Sign-in buttons for each configured provider; renders nothing when none are set up. --}}
@if (! empty($social))
    <div class="mt-6 space-y-2">
        @if (in_array('nikniq', $social, true))
            <a href="{{ route('nikniq.redirect') }}" class="flex w-full items-center justify-center gap-2 rounded border border-indigo-500/60 bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500">
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" opacity=".25" d="M12 1.5 21 6.75v10.5L12 22.5 3 17.25V6.75z"/><path fill="currentColor" d="M8 16.5V7.5h1.9l4.2 5.6V7.5H16v9h-1.9l-4.2-5.6v5.6z"/></svg>
                Continue with nikniq
            </a>
        @endif
        @if (in_array('google', $social, true))
            <a href="{{ route('google.redirect') }}" class="flex w-full items-center justify-center gap-2 rounded border border-stone-700 bg-white px-4 py-2 font-medium text-stone-900 hover:bg-stone-100">
                <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.6 2.5 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.3l7.8 6C12.3 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 7.1-10 7.1-17.5z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.8-6C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.7l7.8-6z"/><path fill="#34A853" d="M24 48c6.2 0 11.6-2 15.4-5.6l-7.5-5.8c-2.1 1.4-4.8 2.3-7.9 2.3-6.3 0-11.7-4.1-13.6-9.8l-7.8 6C6.5 42.6 14.6 48 24 48z"/></svg>
                Continue with Google
            </a>
        @endif
    </div>
    <div class="my-5 flex items-center gap-3 text-xs text-stone-500"><span class="h-px flex-1 bg-stone-800"></span>or use your email<span class="h-px flex-1 bg-stone-800"></span></div>
@endif
