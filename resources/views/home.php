<?php $template->layout('default'); ?>

<main class="mx-auto flex min-h-full max-w-2xl flex-col justify-center px-6 py-16">
    <h1 class="text-4xl font-semibold tracking-tight">Welcome to AppKit</h1>
    <p class="mt-4 text-lg text-gray-600">
        You're looking at the default skeleton. Edit
        <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-sm">src/Controller/HomeController.php</code>
        and <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-sm">resources/views/home.php</code>
        to get started.
    </p>

    <?php if ($user): ?>
        <div class="mt-8 flex items-center gap-3">
            <p class="text-sm text-gray-500">Signed in as <?= $this->esc($user->getEmail()); ?>.</p>
            <form method="POST" action="<?= $this->url('/logout'); ?>" class="inline">
                <input type="hidden" name="_csrf_token" value="<?= $this->esc($logout_csrf, 'attr'); ?>">
                <button type="submit" class="text-sm font-medium text-gray-700 underline hover:text-gray-900">
                    Sign out
                </button>
            </form>
        </div>
    <?php else: ?>
        <p class="mt-8 text-sm text-gray-500">Not signed in.</p>
    <?php endif; ?>

    <ul class="mt-10 grid gap-3 text-sm">
        <li><a class="text-blue-600 hover:underline" href="https://github.com/modufolio/appkit">Documentation</a></li>
        <li><span class="text-gray-500">Routes are declared with <code>#[Route]</code> attributes on controllers.</span></li>
        <li><span class="text-gray-500">DI wiring lives in <code>config/controllers.php</code> and <code>config/services.php</code>.</span></li>
    </ul>
</main>
