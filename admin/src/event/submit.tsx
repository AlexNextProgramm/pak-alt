import { Captcha } from '../module/captcha';
import { ajax } from '../module/ajax';
import { $, Rocet } from '@rocet/rocet';
import { button } from '@rocet/RocetNodeElements';

const submit =  function (evt: MouseEvent) {
    evt.preventDefault();
    let $button = $(evt.target as HTMLElement);
    if ($button.isAttr('data-captcha')) {
        new Captcha(sendData, $button.closest('form'));
    } else {
        sendData()
    }
    function sendData() {
        const form: HTMLFormElement | null = $(evt.target).closest('form').item() as HTMLFormElement;
        if (typeof window['syncEditorsBeforeSubmit'] === 'function') {
            window['syncEditorsBeforeSubmit']();
        }
        ajax.post(form, {}, null, evt.target as HTMLElement).then((data) => {
            let ev = ajax.eventForm(data)
            if (typeof window['callbackSubmit'] === 'function') {
                const result:any = (window['callbackSubmit'] as Function)(data)
                if (result?.preventDefault) {
                    return false;
                }
            }
            if (ev) {
                $('[data-reload]').each(($elm: Rocet) => {
                    $elm.val('')
                })
            }
            }
        );
    }
}

window['submit'] = submit

$('button[type=submit]').on('click', submit);

$('[evt="change-form"]').on('click', function (ev: MouseEvent) {
    ev.preventDefault();
    const $btn = $(this);
    const $form = $btn.closest('form');
    let type = $btn.attr('type');
    if (type == 'submit') {
        submit(ev)
        $btn.attr('type', 'button');
        $btn.classList.replace('btn-change-submit', 'btn-change-button');
        blockForm($form);
    }
    if (type == 'button') {
        $btn.attr('type', 'submit');
        $btn.classList.replace('btn-change-button', 'btn-change-submit');
        unlockForm($form)
    }


    function unlockForm($form: Rocet) { 
        $form.find('.masked-p').attrRemove('disabled');
        $form.find('.masked-p').find('[type=checkbox]').attrRemove('disabled');
        $form.find('.masked-p').attr('class', 'unmasked');
        $form.find('.select-hide').classReplase('select-hide', 'select');
        $form.find('.box').classList.remove('box-hide');
    }
    function blockForm($form: Rocet) { 
        $form.find('.unmasked').attr('disabled', '');
        $form.find('.unmasked').find('[type=checkbox]').attr('disabled', '');
        $form.find('.unmasked').attr('class', 'masked-p');
        $form.find('.select').classReplase('select', 'select-hide');
        $form.find('.box').classAdd('box-hide');
    }
})

$('[evt="back"]').on('click', function () { 
    history.back();
})