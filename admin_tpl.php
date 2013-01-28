<h1><?php echo $this->getLang('admin headline')?></h1>
<br />
<?php
if (!empty($this->message)) {
    msg($this->getLang(($this->message)));
}
?>
<form action="doku.php" method="post">
    <div class="no">
        <input type="hidden" name="do" value="admin"/>
        <input type="hidden" name="page" value="tagging"/>
        <input type="hidden" name="id" value="<?php echo hsc($ID)?>"/>
        <input type="hidden" name="sectok" value="<?php echo hsc(getSecurityToken())?>"/>
    </div>
    <h3><?php echo $this->getLang('admin rename tag')?></h3>
    <table class="inline">
        <tr>
            <th><?php echo $this->getLang('admin find tag')?></th>
            <th><?php echo $this->getLang('admin new name')?></th>
            <th></th>
        </tr>
        <tr>
            <td><input id="tags" type="text" name="action[formerTagName]" class="edit" /></td>
            <td><input type="text" name="action[newTagName]" /></td>
            <td><input type="submit" name="action[rename]" value="<?php echo $this->getLang('admin save')?>" class="button"/></td>
        </tr>
    </table>
</form>

<table class="inline">
    <tr>
        <th><?php echo $this->getLang('admin tag')?></th>
        <th><?php echo $this->getLang('admin occurrence')?></th>
    </tr>

    <?php foreach ($tags as $tagname => $taginfo): ?>
        <?php $tagname = hsc($tagname); ?>
        <tr>
            <td>
                <a class="tagslist" href="?do=search&amp;id=<?php echo $tagname . "#" . str_replace(' ', '_', strtolower($this->getLang('search_section_title'))); ?>">
                    <?php echo $tagname; ?>
                </a>
            </td>
            <td>
                <?php echo $taginfo['count'] ?>
            </td>

        </tr>
    <?php endforeach; ?>
</table>