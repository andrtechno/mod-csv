{use class="Yii"}

{if $warnings}
    <div><strong>{Yii::t('csv/default','WARNING_IMPORT')}</strong></div>
{foreach from=$warnings key=key item=warning}
    <div><strong>{Yii::t('csv/default','LINE')}:</strong> {$warning.line} > {$warning.error}</div>
{/foreach}
{/if}

{if $errors}
    <div><strong>{Yii::t('csv/default','ERRORS_IMPORT')}</strong></div>
    {foreach from=$errors key=key item=error}
        <div><strong>{Yii::t('csv/default','LINE')}:</strong> {$error.line} > {$error.error}</div>
    {/foreach}
{/if}

