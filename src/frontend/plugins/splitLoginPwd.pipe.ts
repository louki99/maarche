import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
    name: 'splitLoginPwd'
})
export class SplitLoginPwdPipe implements PipeTransform {

    constructor() { }

    transform(url: string): string {
        if (url.indexOf('@') > -1) {
            const protocole: string = url.substring(0, url.indexOf('://'));
            const serverName: string = url.substring(url.indexOf('@') + 1, url.length)
            const URL: string = protocole.concat('://').concat(serverName);
            return URL;
        } else {
            return url;
        }
    }
}