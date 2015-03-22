<div class="narrow">

<? if($this->subscription->active): ?>
  <div class="bs bs-callout bs-callout-success">This subscription is active!</div>
<? else: ?>
  <div class="bs bs-callout bs-callout-danger">This subscription is not active</div>
<? endif; ?>
<table class="table">

  <?php $tz = -7 * 3600; ?>

  <tr>
    <td>Feed URL (Topic)</td>
    <td><?= $this->feed->feed_url ?></td>
  </tr>
  <tr>
    <td>Callback URL</td>
    <td><?= $this->subscription->callback_url ?></td>
  </tr>
  <tr>
    <td>Date Subscription was Requested</td>
    <td><?= $this->subscription->date_requested ? friendly_date($this->subscription->date_requested, $tz) : '' ?></td>
  </tr>
  <tr>
    <td>Subscription Verification Response (from your server)</td>
    <td><pre><?= htmlspecialchars($this->subscription->challenge_response) ?></pre></td>
  </tr>
  <tr>
    <td>Date Subscription was Confirmed</td>
    <td><?= $this->subscription->date_confirmed ? friendly_date($this->subscription->date_confirmed, $tz) : '' ?></td>
  </tr>
  <tr>
    <td>Subscription Expiration</td>
    <td><?= $this->subscription->date_expires ? friendly_date($this->subscription->date_expires, $tz) : '' ?></td>
  </tr>


</table>

</div>