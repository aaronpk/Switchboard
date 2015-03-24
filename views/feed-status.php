<?php $tz = 0; ?>
<div class="narrow subscription-status">

<h3>Feed Status</h3>
<table class="table">
  <tr>
    <td>URL</td>
    <td><?= $this->feed->feed_url ?></td>
  </tr>
  <tr>
    <td>Last Ping Received</td>
    <td><?= format_date($this->feed->push_last_ping_received, $tz) ?></td>
  </tr>
  <tr>
    <td>Last Retrieved</td>
    <td><?= format_date($this->feed->last_retrieved, $tz) ?></td>
  </tr>
</table>

<h3>Subscribers</h3>
<table class="table">

<tr>
  <td colspan="2">
    <?= $this->num_subscribers ?> active subscribers
  </td>
</tr>

<? foreach($this->subscribers as $subscriber): ?>
  <tr>
    <td colspan="2"><h4><?= $subscriber->callback_url ?></h4></td>
  </tr>
  <tr>
    <td>Date Subscribed</td>
    <td><?= format_date($subscriber->date_created, $tz) ?></td>
  </tr>
  <tr>
    <td>Last Confirmed</td>
    <td><?= format_date($subscriber->date_confirmed, $tz) ?></td>
  </tr>
  <tr>
    <td>Date Expires</td>
    <td><?= format_date($subscriber->date_expires, $tz) ?></td>
  </tr>
  <tr>
    <td>Last Ping Sent</td>
    <td><?= format_date($subscriber->date_last_ping_sent, $tz) ?></td>
  </tr>
  <tr>
    <td>Last Response<br>(from subscriber)</td>
    <td><pre><?= htmlspecialchars($subscriber->last_ping_headers."\n\n".$subscriber->last_ping_body) ?></pre></td>
  </tr>
  <tr>
    <td>Last ping was successful?</td>
    <td><?= $subscriber->last_ping_success ? 'Yes' : 'No' ?><br>(Subscriber must return 2xx on success)</td>
  </tr>
  <? if($subscriber->last_ping_success == 0): ?>
    <tr>
      <td>Retrying ping in</td>
      <td>
        <?= $subscriber->last_ping_error_delay/2 ?> seconds<br>
        (Will be de-activated after 1 hour from first failed ping)
      </td>
    </tr>
  <? endif; ?>
<? endforeach; ?>

</table>

</div>
