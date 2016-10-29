<div class="narrow">

  <div class="jumbotron">
    <h1><a href="/"><img src="/images/switchboard-logo.png" height="72" style="margin-bottom: 13px;" class="" alt="logo"></a> Switchboard</h1>

    <h2>Publishing</h2>

    <p>To publish content using Switchboard as your hub, add the following tags to your HTML feeds:</p>
    <p><pre>&lt;link rel="self" href="https://example.com/"&gt;
&lt;link rel="hub" href="https://switchboard.p3k.io/"&gt;</pre></p>

    <p>When you add a new item to the feed, send a POST request to <code>https://switchboard.p3k.io/</code> with the following 
      parameters:</p>

    <p>
      <ul>
        <li><code>hub.mode=publish</code></li>
        <li><code>hub.topic=https://example.com/</code></li>
      </ul>
    </p>

    <p>Switchboard will send notifications to every subscriber that your feed has been updated. The request that Switchboard sends to your subscribers will be a POST request with no body. This is known as a "thin ping". Your subscribers will then request your feed to find updates.</p>



    <h2>Subscribing</h2>

    <p>If you are subscribing to a feed that uses Switchboard as its hub, here is what you can expect.</p>

    <h3>Verification</h3>

    <p>When you first request the subscription, Switchboard will send a verification request to your callback URL. The verification request will be a GET request to your callback URL with the following query parameters:</p>

    <p>
      <ul>
        <li><code>hub.mode=subscribe</code></li>
        <li><code>hub.topic=</code> the topic URL that you requested to subscribe to</li>
        <li><code>hub.challenge=</code> a random string that you will need to echo to confirm the subscription</li>
        <li><code>hub.lease_seconds=</code> the number of seconds this subscription will remain active</li>
      </ul>
    </p>

    <p>To confirm the subscription, you will need to respond with <code>HTTP 200</code> and the body of the response must be exactly equal to the challenge string. Any other response will not activate your subscription.</p>

    <h3>Notifications</h3>

    <p>When there is new content available from the topic URL, Switchboard will send a POST request to your callback URL.</p>

    <p>This POST request will have no body, and is meant to be an indication that the URL has been updated, and that you can fetch the new contents from there.</p>


  </div>

</div>
