import './styles.css';

const keyFiles = [
  'README.md',
  'composer.json',
  '.github/workflows/ci.yml',
  'src/Httpful/Client.php',
  'src/Httpful/Request.php',
  'src/Httpful/Response.php',
  'src/Httpful/Factory.php',
  'src/Httpful/ClientMulti.php',
  'src/Httpful/Setup.php',
  'src/Httpful/Mime.php',
  'tests/Httpful/',
  'examples/',
];

const app = document.querySelector('#app');

if (!app) {
  throw new Error('App root not found.');
}

const prompt = `You are reviewing the voku/httpful repository.\n\nStart with these key files and explain why each one matters before exploring anything else:\n${keyFiles
  .map((file) => `- ${file}`)
  .join('\n')}\n\nThen identify any additional files that are critical for the task at hand, grouped by API surface, transport internals, test coverage, and release automation.\nReturn the result as a prioritized checklist with short explanations.`;

app.innerHTML = `
  <main class="page">
    <section class="hero">
      <div class="hero__content">
        <p class="eyebrow">voku/httpful</p>
        <h1>Readable HTTP workflows for modern PHP applications.</h1>
        <p class="lede">
          Httpful gives you a fluent cURL-powered API with automatic parsing, PSR compatibility,
          async helpers, and curl-multi support for production integrations.
        </p>
        <div class="hero__actions">
          <a class="button button--primary" href="https://packagist.org/packages/voku/httpful">Install from Packagist</a>
          <a class="button button--secondary" href="https://github.com/voku/httpful/blob/master/README.md">Read the README</a>
        </div>
        <ul class="stats" aria-label="Project highlights">
          <li><strong>PHP 8.0+</strong><span>Runtime target</span></li>
          <li><strong>PSR-3/7/17/18</strong><span>Interop ready</span></li>
          <li><strong>curl_multi</strong><span>Parallel execution</span></li>
        </ul>
      </div>
      <div class="panel code-panel">
        <div class="panel__label">Quick example</div>
<pre><code>use Httpful\\Request;

$response = Request::get('https://api.example.com/items')
    ->expectsJson()
    ->withBearerToken('secret-token')
    ->withRetry(3)
    ->withRetryDelay(1)
    ->send();

$items = $response->getRawBody();</code></pre>
      </div>
    </section>

    <section class="section grid grid--3">
      <article class="card">
        <h2>Fluent request builders</h2>
        <p>Build requests with readable method helpers, chain transport options, and keep your integration code compact.</p>
      </article>
      <article class="card">
        <h2>Production controls</h2>
        <p>Configure retries, timeouts, TLS bundles, proxies, cookie jars, redirects, and downloads without dropping to raw curl options.</p>
      </article>
      <article class="card">
        <h2>Interop and extensibility</h2>
        <p>Use PSR interfaces, custom mime handlers, global error handlers, and async helpers in larger frameworks or service layers.</p>
      </article>
    </section>

    <section class="section split">
      <div>
        <p class="eyebrow">Install</p>
        <h2>Ship quickly with the existing API.</h2>
        <p>Httpful works well for JSON APIs, form posts, downloads, scraping, and lower-level transport customization.</p>
        <pre><code>composer require voku/httpful</code></pre>
      </div>
      <div class="panel">
        <div class="panel__label">What you get</div>
        <ul class="feature-list">
          <li>Automatic JSON, XML, HTML, CSV, and form parsing</li>
          <li>Async promises via <code>sendAsync()</code></li>
          <li>Parallel request execution with <code>ClientMulti</code></li>
          <li>PSR request, response, client, and factory support</li>
          <li>Extensible mime registration through <code>Setup</code></li>
        </ul>
      </div>
    </section>

    <section class="section split split--reverse">
      <div class="panel code-panel">
        <div class="panel__label">Parallel requests</div>
<pre><code>$multi = new Httpful\\ClientMulti(
    static function (Httpful\\Response $response): void {
        echo $response->getCode() . PHP_EOL;
    }
);

$multi
    ->add_get('https://postman-echo.com/get?name=one')
    ->add_get('https://postman-echo.com/get?name=two');

$multi->start();</code></pre>
      </div>
      <div>
        <p class="eyebrow">Scale out</p>
        <h2>Use async and curl-multi helpers for fan-out workloads.</h2>
        <p>Keep the same request model while batching downloads, API lookups, or concurrent outbound calls.</p>
      </div>
    </section>

    <section class="section">
      <p class="eyebrow">Key Files Detector</p>
      <div class="panel prompt-panel">
        <div class="prompt-panel__header">
          <h2>Helper prompt</h2>
          <button id="copy-prompt" class="button button--secondary" type="button">Copy prompt</button>
        </div>
        <pre><code id="prompt-text"></code></pre>
      </div>
    </section>
  </main>
`;

const promptNode = document.querySelector('#prompt-text');
if (promptNode) {
  promptNode.textContent = prompt;
}

const copyButton = document.querySelector('#copy-prompt');
if (copyButton instanceof HTMLButtonElement) {
  copyButton.addEventListener('click', async () => {
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(prompt);
      } else {
        throw new Error('Clipboard API unavailable');
      }

      copyButton.textContent = 'Copied';
    } catch (error) {
      console.error(error);
      copyButton.textContent = 'Copy unavailable';
    }

    window.setTimeout(() => {
      copyButton.textContent = 'Copy prompt';
    }, 1500);
  });
}
