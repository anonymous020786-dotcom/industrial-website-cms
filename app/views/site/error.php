<?php /** @var int $status @var string $message */ ?>
<section class="blueprint flex min-h-[70vh] items-center bg-navy text-white">
  <div class="mx-auto w-full max-w-site px-4 py-24 sm:px-6 lg:px-8">
    <p class="font-display text-[120px] font-bold leading-none text-safety sm:text-[180px]"><?= (int) $status ?></p>
    <h1 class="mt-2 font-display text-4xl font-bold uppercase tracking-tight sm:text-5xl"><?= $status === 404 ? 'Page Not Found' : 'Something Went Wrong' ?></h1>
    <p class="mt-4 max-w-lg text-lg text-white/70"><?= $status === 404 ? 'The page you’re looking for has moved or doesn’t exist. Try one of these instead:' : e($message) ?></p>
    <div class="mt-8 flex flex-wrap gap-3">
      <a href="<?= e(url('')) ?>" class="btn btn-primary">Back to Home <?= icon('arrow-right', 'btn-arrow h-4 w-4') ?></a>
      <a href="<?= e(url('products')) ?>" class="btn btn-ghost-light">Browse Products</a>
      <a href="<?= e(url('blog')) ?>" class="btn btn-ghost-light">Read the Blog</a>
      <a href="<?= e(url('contact')) ?>" class="btn btn-ghost-light">Contact Us</a>
    </div>
  </div>
</section>
