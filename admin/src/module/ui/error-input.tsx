import { integ } from "@rocet/integration";
import { $, Rocet } from "@rocet/rocet";

export class ErrorInput{ 
    public name = '';
    constructor(data: any) { 
        this.name = data.name;
        this.color()
        this.message(data.message);
    }

     color(){
         $('[name=' + this.name + ']').classAdd('in-err');
     }
    message(str:string) { 
        $('.error').show();
        let add = true;
        $('.error').find('p').each((el:Rocet) => { 
            if (el.text() == str) { 
                add = false;
            }
        })
        if (add) { 
            $('.error').add(<p>{str}</p>);
        }
    }
}