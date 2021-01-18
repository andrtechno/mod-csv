{use class="Yii"}

{if $warnings}
    <div><strong>{Yii::t('csv/default','WARNING_IMPORT')}</strong></div>
    {foreach from=$warnings key=key item=warning}
        <div>{$warning.type}, <strong>{Yii::t('csv/default','LINE',$warning.line)}</strong> {$warning.error}</div>
    {/foreach}
{/if}

{if $errors}
    <div><strong>{Yii::t('csv/default','ERRORS_IMPORT')}</strong></div>
    {foreach from=$errors key=key item=error}
        <div>{$error.type}, <strong>{Yii::t('csv/default','LINE',$error.line)}</strong> {$error.error}</div>
    {/foreach}
{/if}

