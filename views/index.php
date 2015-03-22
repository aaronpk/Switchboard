<div class="narrow">

  <div class="jumbotron h-x-app">
    <h1><img src="/images/switchboard-logo.png" height="72" style="margin-bottom: 13px;" class="u-logo p-name" alt="Switchboard"> Switchboard</h1>

    <p class="tagline p-summary">Switchboard is a PubSubHubbub 0.4 hub.</p>

    <p>To publish content using Switchboard as your hub, add the following links to your home page:</p>
    <p><pre>&lt;link rel="self" href="https://example.com/"&gt;
&lt;link rel="hub" href="https://switchboard.p3k.io/"&gt;</pre></p>

    <p>Then, send a POST request to <code>https://switchboard.p3k.io/</code> with the following 
      parameters every time you add content to your home page:</p>

    <p>
      <ul>
        <li><code>hub.mode=publish</code></li>
        <li><code>hub.topic=https://example.com/</code></li>
      </ul>
    </p>

    <p>Read more info about <a href="https://indiewebcamp.com/how-to-push">how to publish and consume using PubSubHubbub</a>.</p>

    <a href="<?= Config::$base_url ?>/" class="u-url"></a>
  </div>

</div>
