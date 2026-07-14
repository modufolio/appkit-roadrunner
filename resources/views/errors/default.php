<?php $template->layout('default'); ?>

<main class="grid min-h-full place-items-center px-6 py-24 sm:py-32 lg:px-8">
    <div class="text-center">
        <p class="text-sm font-semibold text-gray-500"><?= $this->esc((string) $status); ?></p>
        <h1 class="mt-3 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">
            <?= $this->esc($title); ?>
        </h1>
        <?php if (!empty($detail)): ?>
            <p class="mt-4 text-base text-gray-600"><?= $this->esc($detail); ?></p>
        <?php endif; ?>
        <div class="mt-8 flex items-center justify-center gap-x-4">
            <a href="<?= $this->url('/'); ?>" class="rounded-md bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-gray-800">
                Go home
            </a>
            <a href="javascript:history.back()" class="text-sm font-medium text-gray-700 hover:text-gray-900">
                Go back <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
</main>
