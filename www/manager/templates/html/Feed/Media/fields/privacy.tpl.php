<label for="privacy">Privacy</label>
<div class="mh-tooltip hang-right" id="privacy-details">
    <?php echo $savvy->render('privacy tooltip', 'InfoIcon.tpl.php'); ?>
    <div>
        <ul>
            <li>
                <span class="heading">Public</span> - Anyone can access the media.
            </li>
            <li>
                <span class="heading">Unlisted</span> - Media will not be included in public MediaHub listings.
            </li>
            <li>
              <span class="heading">Protected</span> - Only logged in users can access the media.
            </li>
            <li>
                <span class="heading">Private</span> - Only members of channels that the media is included in can access it.
            </li>
        </ul>
    </div>
</div>
<select class="dcf-input-select" id="privacy" name="privacy" aria-describedby="privacy-details">
<?php
foreach (UNL_MediaHub_Media::getPossiblePrivacyValues() as $value) {
  $selected = '';
  if ($value == @$context->media->privacy) {
    $selected = 'selected="selected"';
  }
  echo "<option value='$value' " . $selected . ">" . ucfirst(strtolower($value)) . "</option>";
}
?>
</select>
