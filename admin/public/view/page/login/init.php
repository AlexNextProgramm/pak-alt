<!DOCTYPE html>
<html lang="ru">
<?

use App\Form\Form; ?>
<? include __DIR__ . '/head.php' ?>

<body>
    <? //view('header'); ?>
    <div class="content">
        <div class="wrapper">
            <div class="block_logo">
                
            </div>
            <div class="block_form">
                <form name="login" csrf-token="<?= Form::csrf() ?>">
                    <h3>Вход</h3>
                    <input ui="text-form" label="Логин" type="text" placeholder="телефон" name="login" data-error="" />
                    <input ui="password-form" label="Пароль" type="password" placeholder="Пароль" name="password" />
                    <!-- <a class="link" href="/forgot">Забыли пароль? </a> -->
                    <div class="flex-row">
                        <button type="submit" class="btn btn-submit-blue" data-captcha>Войти</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <? //view('footer'); ?>
</body>

</html>