import { $ } from "@rocet/rocet";
import { Fire } from "./ui/fire";
import { input } from "@rocet/RocetNodeElements";
import { inputTextFormRender } from "@tools/UI/input";
import { switchFormRender } from "./ui/switch";
import { photoGalleryRender } from "./ui/photo-gallery";
import { ErrorInput } from "./ui/error-input";

export class ajax {
    static async send(name:string, body:any, isJson:boolean = true) {
        const result = await ajax.post(body, {}, '/ajax/' + name);
        return typeof result === 'string' ? JSON.parse(result) : result;
    }

    static async sendApi(name:string, body:any, isJson:boolean = true) {
        const result = await ajax.post(body, {}, '/api/ajax/' + name);
        return typeof result === 'string' ? JSON.parse(result) : result;
    }

    static async post(data: BodyInit | null | any | HTMLFormElement, headers:any = {}, href:string = null, submiter?:HTMLElement) {
        let body: any;
        let url: string = href || location.href;
        if (data instanceof HTMLFormElement) {
            body = new FormData(data, submiter);
            this.checkboxNormalize(data, body);
            if (data.hasAttribute('name')) { 
                headers['form-name'] = data.getAttribute('name');
            }

            body.append('csrf-token', data.getAttribute('csrf-token'));
        } else { 
            body = new FormData();
            Object.keys(data).forEach((key: string) => {
                if (typeof data[key] == 'object') {
                    body.append(key, JSON.stringify(data[key]));
                } else {
                    body.append(key, data[key]);
                }
            });
        }

       const result =  await fetch(url, {
            method: 'POST',
            body,
            headers,
            redirect: 'follow'
       })
        const contentType = result.headers.get('Content-Type') ?? result.headers.get('Content-type') ?? '';
        if (contentType.includes('application/json')) {
            return await result.json();
        }
        return await result.text();
    }

    public static eventForm(data: any = {}): boolean
    {
        if (data?.type == 'fire') {
            return Fire.show(data);
        }
        if (data?.type == 'error-input') {
            $('.in-err').classRemove('in-err');
            $('.error').html(' ');
            if (Array.isArray(data.name)) {
                data.name.forEach((e: any, i: any) => {
                    new ErrorInput({
                        message: data.message[i],
                        name: e
                    });
                    
                });
            } else { 
                new ErrorInput(data);
            }
            return false;
        }
        if (data?.type ==  'modal') ajax.modalOpen(data)
        if (data?.type == 'reload') location.reload();
        if (data?.type == 'redirect') location.href = data.href;
        return true;
    }

    public static modalOpen(data: any , callback?:Function) {
        const callbackModal = data['callbackModal'] || null;

        ajax.post(data, {}, '/modal').then((response) => {
            const parse = JSON.parse(response)
            const body = document.body
            $('.modal-carset').remove();
            const modal:HTMLElement = document.createElement('div') as HTMLElement
            modal.classList.add('modal-carset');
            modal.innerHTML = parse.html;
            body.append(modal);

            $('input[ui=text-form]').render(inputTextFormRender)
            $('input[ui=switch]').render(switchFormRender)
            $('[ui=photo-gallery]').render(photoGalleryRender)
            $(modal).find('button[type=submit]').on('click', function (evt: MouseEvent) {
                evt.preventDefault();
                const $button = $(evt.target as HTMLElement)
                const form: HTMLFormElement = $button.closest('form').item(0) as  HTMLFormElement;
                ajax.post(form).then((dataForm) => ajax.eventForm(dataForm));
            });

            $('.modal-close').on('click', () => { 
                modal.remove();
            })
            if (typeof window[callbackModal] === 'function') {
                (window[callbackModal] as Function)(data)
            }
            if (callback) { 
                callback()
            }
        });
    }


    private  static checkboxNormalize(form:HTMLFormElement , fdata:FormData) { 
        form.querySelectorAll('input[type=checkbox]').forEach((el:HTMLInputElement) => { 
            fdata.delete(el.name);
            fdata.append(el.name, String(el.checked ? 1 : 0));
        })
    }
}