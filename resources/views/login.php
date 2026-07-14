<?php $template->layout('default'); ?>

<main class="grid min-h-full place-items-center bg-gradient-to-b from-gray-50 to-gray-100 px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-900 text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">Welcome back</h1>
            <p class="mt-1 text-sm text-gray-500">Sign in to your account to continue</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <?php if (!empty($error)): ?>
                <div class="mb-5 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
                    <svg class="mt-0.5 h-4 w-4 flex-none" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= $this->esc($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login" class="space-y-5">
                <input type="hidden" name="_csrf_token" value="<?= $this->esc($csrf_token, 'attr'); ?>">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" type="email" name="email" required autofocus autocomplete="email"
                           placeholder="you@example.com"
                           class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="#" class="text-xs font-medium text-gray-500 hover:text-gray-900">Forgot password?</a>
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="_remember_me" value="1"
                           class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                    Remember me
                </label>

                <button type="submit"
                        class="flex w-full items-center justify-center rounded-lg bg-gray-900 px-3 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">
                    Sign in
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-gray-500">
            Protected by AppKit · <a href="/" class="hover:text-gray-900">Back to home</a>
        </p>
    </div>
</main>
