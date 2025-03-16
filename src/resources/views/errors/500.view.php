<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 Internal Server Error</title>
  <style>
    :root {
      --color-error: #dc2626;
      --color-error-bg: #fef2f2;
      --color-code-bg: #f8fafc;
      --color-text: #1e293b;
      --color-text-light: #64748b;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      line-height: 1.6;
      margin: 0;
      padding: 2rem;
      background-color: var(--color-error-bg);
      color: var(--color-text);
    }

    .error-container {
      max-width: 80rem;
      margin: 2rem auto;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .error-header {
      padding: 1.5rem;
      background: var(--color-error);
      color: white;
    }

    .error-title {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
    }

    .error-content {
      padding: 1.5rem;
    }

    .code-snippet {
      background: var(--color-code-bg);
      border-radius: 6px;
      margin: 1.5rem 0;
      overflow: hidden;
    }

    .code-header {
      padding: 0.75rem 1rem;
      background: #e2e8f0;
      font-family: Menlo, Monaco, Consolas, monospace;
      font-size: 0.875rem;
    }

    .code-content {
      padding: 1rem;
      overflow-x: auto;
    }

    .code-line {
      display: flex;
      font-family: Menlo, Monaco, Consolas, monospace;
      font-size: 0.875rem;
    }

    .line-number {
      width: 40px;
      padding-right: 1rem;
      color: var(--color-text-light);
      user-select: none;
      text-align: right;
    }

    .error-line {
      background: #fff0f0;
      border-left: 3px solid var(--color-error);
    }

    .stack-trace {
      margin-top: 2rem;
    }

    .stack-title {
      font-size: 1.125rem;
      margin: 1.5rem 0 1rem;
    }

    .stack-frame {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      margin-bottom: 0.75rem;
      padding: 1rem;
    }

    .stack-meta {
      color: var(--color-text-light);
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
    }

    .stack-call {
      font-family: Menlo, Monaco, Consolas, monospace;
      font-size: 0.875rem;
    }

    .debug-info {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #e2e8f0;
      font-size: 0.875rem;
      color: var(--color-text-light);
    }

    .error-fallback {
      display: none;
      background: #f8d7da;
      padding: 1rem;
      margin: 1rem 0;
    }

    .error-message-title {
      color: #dc2626;
      background: #fff0f0;
      border-left: 3px solid var(--color-error);
      border-radius: 6px;
      padding: .4rem;
    }
  </style>
</head>

<body>
  <div class="error-container">
    <div class="error-header">
      <h1 class="error-title">500 Internal Server Error</h1>
    </div>

    <div class="error-content">
      <p class="error-message">
        <strong>Something went wrong while processing your request.</strong><br>
      </p>

      <p class="error-message-title">
        <?= htmlspecialchars($message) ?>
      </p>


      <?php if (config('app.debug')): ?>
        <?php if (!empty($snippet)): ?>
          <div class="code-snippet">
            <?php if (!empty($snippet['file'])): ?>
              <div class="code-header">
                Error in <?= htmlspecialchars($snippet['file'] ?? 'unknown file') ?>:<?= $snippet['error_line'] ?? 'unknown' ?>
                <?php if (($snippet['context']['is_compiled'] ?? false) && isset($snippet['original_line'])): ?>
                  (Originally line <?= $snippet['original_line'] ?>)
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="code-content">
              <?php if (!empty($snippet['content'])): ?>
                <?php foreach ($snippet['content'] as $index => $line): ?>
                  <?php
                  $lineNumber = ($snippet['line_start'] ?? 0) + $index;
                  $isErrorLine = $lineNumber === ($snippet['error_line'] ?? -1);
                  ?>
                  <div class="code-line <?= $isErrorLine ? 'error-line' : '' ?>">
                    <span class="line-number"><?= $lineNumber ?></span>
                    <code><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></code>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="no-code">Unable to load code snippet</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($trace)): ?>
          <div class="stack-trace">
            <h2 class="stack-title">Stack Trace</h2>
            <?php foreach ($trace as $frame): ?>
              <div class="stack-frame">
                <?php if ($frame['file']): ?>
                  <div class="stack-meta">
                    <?= htmlspecialchars($frame['file']) ?>:<?= $frame['line'] ?>
                  </div>
                <?php endif; ?>
                <div class="stack-call">
                  <?= htmlspecialchars($frame['call']) ?>
                </div>
                <?php if (!empty($frame['source'])): ?>
                  <div class="code-snippet" style="margin-top: 0.5rem;">
                    <div class="code-content">
                      <?php foreach ($frame['source']['content'] as $index => $line): ?>
                        <?php $lineNumber = $frame['source']['line_start'] + $index; ?>
                        <div class="code-line">
                          <span class="line-number"><?= $lineNumber ?></span>
                          <code><?= htmlspecialchars($line) ?></code>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="debug-info">
          <p>Environment: <?= env('APP_ENV') ?> (PHP <?= phpversion() ?>)</p>
          <p>Request: <?= $_SERVER['REQUEST_METHOD'] ?? 'CLI' ?> <?= $_SERVER['REQUEST_URI'] ?? '' ?></p>
        </div>
      <?php else: ?>
        <p>Our technical team has been notified of this error. Please try again later.</p>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>
