<?php echo $header;  ?>
<div id="content">
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) {  ?>
    <?php echo $breadcrumb['separator'];  ?><a href="<?php echo $breadcrumb['href'];  ?>"><?php echo $breadcrumb['text'];  ?></a>
    <?php }  ?>
  </div>
  <?php if ($error_warning) {  ?>
  <div class="warning"><?php echo $error_warning;  ?></div>
  <?php }  ?>
  <?php if ($success) {  ?>
  <div class="success"><?php echo $success;  ?></div>
  <?php }  ?>
  <div class="box">
    <div class="heading">
      <h1><img src="view/image/arrow.png" alt="" /> <?php echo $heading_title;  ?></h1>
      <div class="buttons">
          <a onclick="$('input[name=Go]').trigger('click');" class="button"> Import </a>
      </div>
    </div>
    <div class="content">
      <form method="post" id="import" enctype="multipart/form-data">
        <table border="0" align="center">
          <tr>
            <td>Source CSV file to import:</td>
            <td rowspan="30" width="10px">&nbsp;</td>
            <td><input type="file" name="file_source" id="file_source" class="edt" value="<?php echo $file_source ?>"></td>
          </tr>
          <tr>
            <td>Use CSV header:</td>
            <td><input type="checkbox" name="use_csv_header" id="use_csv_header" <?php echo (isset($_POST["use_csv_header"])? "checked":"checked") ?>/></td>
          </tr>
          <tr>
            <td>Separate char:</td>
            <td><input type="text" name="field_separate_char" id="field_separate_char" class="edt_30"  maxlength="1" value="<?php echo (""!=$field_separate_char ? htmlspecialchars($_POST["field_separate_char"]) : ",") ?>"/></td>
          </tr>
          <tr>
            <td>Enclose char:</td>
            <td><input type="text" name="field_enclose_char" id="field_enclose_char" class="edt_30"  maxlength="1" value="<?php echo (""!=$field_enclose_char ? htmlspecialchars($_POST["field_enclose_char"]) : htmlspecialchars("\"")) ?>"/></td>
          </tr>
          <tr>
            <td>Escape char:</td>
            <td><input type="text" name="field_escape_char" id="field_escape_char" class="edt_30"  maxlength="1" value="<?php echo (""!=$field_escape_char ? htmlspecialchars($_POST["field_escape_char"]) : "\\") ?>"/></td>
          </tr>
          <tr>
            <td>Encoding:</td>
            <td>
              <select name="encoding" id="encoding" class="edt">
              <?
                if(!empty($arr_encodings))
                  foreach($arr_encodings as $charset=>$description):
               ?>
                <option value="<?php echo $charset ?>"<?php echo ($charset == $_POST["encoding"] ? "selected=\"selected\"" : "") ?>><?php echo $description ?></option>
              <? endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td colspan="3">&nbsp;</td>
          </tr>
          <tr>
            <td colspan="3" align="center">
              <input type="Submit" name="Go" value="Import it" onclick=" var s = document.getElementById('file_source'); if(null != s && '' == s.value) {alert('Define file name'); s.focus(); return false;}"></td>
          </tr>
        </table>
      </form>
    </div>
  </div>
</div>
<div>

  Made with love by patrick mutwiri
  https://twitter.com/patric_mutwiri
  https://patric.xyz

  Wanna buy come coffee?
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
  <input type="hidden" name="cmd" value="_s-xclick">
  <input type="hidden" name="hosted_button_id" value="DDNH54DSNYGAL">
  <input type="image" src="https://patric.xyz/images/logo/logo.png" border="0" name="submit" alt="ke Developer">
  <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=DDNH54DSNYGAL">Buy Coffee</a>
</div>
<?php echo $footer;  ?>