import { $ } from "@rocet/rocet";
import { Cookie } from "../cookie";
import { ajax } from "../ajax";

$('[evt="btn-roll-up-li"]').on('click', (ev: MouseEvent) => {
    let $btn = $(ev.target);
    let $libtn = $btn.find('button');
    if ($libtn.length != 0) $btn = $libtn;
    $btn.classToggle('btn-roll-up-close');
    $('.menu').classList.toggle('menu-hide');
    if ($('.menu').classList.contains('menu-hide')) {
        Cookie.set('menu', '1');
    } else { 
        Cookie.delete('menu')
    }

})
$('[evt=exit]').on('click', (ev: MouseEvent) => {
    Cookie.delete('auth');
    location.reload();
})

$('[evt="btn-sit-master"]').on('click', function () { 
    ajax.modalOpen({
        template: 'sitmaster',
        header: 'Подтверждение стать Мастером',
        form_name: 'user/sitmaster'
    })
})