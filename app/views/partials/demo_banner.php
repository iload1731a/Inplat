<?php declare(strict_types=1); ?>
<?php if ((bool)config('app.demo_mode')): ?>
<div class="demo-banner d-flex align-items-center justify-content-center gap-2 py-2 px-3 text-center"
     style="background:#92400e;color:#fef3c7;font-size:.85rem;position:sticky;top:0;z-index:2000;">
    <i class="fa fa-flask"></i>
    <strong>Demo Mode</strong>
    — This is a read-only demo installation. Write actions are disabled.
    &nbsp;|&nbsp;
    Admin: <code style="color:#fde68a;">admin@demo.test</code> / <code style="color:#fde68a;">Demo@1234</code>
    &nbsp;|&nbsp;
    User: <code style="color:#fde68a;">user@demo.test</code> / <code style="color:#fde68a;">Demo@1234</code>
</div>
<?php endif; ?>
