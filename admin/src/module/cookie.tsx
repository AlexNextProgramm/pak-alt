export class Cookie { 

    static get(name: string): string | undefined
    { 
        let matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
          ));
          return matches ? decodeURIComponent(matches[1]) : undefined;
    }

    static set(name:string, value:string , options:any={} ) {
        options = {
            path: '/',
            SameSite:'Lax',
            // при необходимости добавьте другие значения по умолчанию
            ...options
          };
        
          if (options.expires instanceof Date) {
            options.expires = options.expires.toUTCString();
          }
        
          let updatedCookie = encodeURIComponent(name) + "=" + encodeURIComponent(value);
          for (let optionKey in options) {
            updatedCookie += "; " + optionKey;
            let optionValue = options[optionKey];
            if (optionValue !== true) {
              updatedCookie += "=" + optionValue;
            }
          }
        
          document.cookie = updatedCookie;
    }

    static delete(name: string){
        Cookie.set(name, "", {
            'max-age': -1})
    }

    static toggle(name: string, value:string = "1") {
        Cookie.get(name) ? Cookie.set(name, value) : Cookie.delete(name);
    }
}
