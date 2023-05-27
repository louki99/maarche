import { Pipe, PipeTransform } from '@angular/core';
import { LatinisePipe } from 'ngx-pipes';



@Pipe({
    name: 'filterList'
})
export class FilterListPipe implements PipeTransform {

    constructor(private latinisePipe: LatinisePipe) { }

    transform(value: any, args: string, id: string): any {
        if (id !== undefined) {
            const filter = args.toLocaleLowerCase();
            return filter ? value.filter((elem: any) => this.latinisePipe.transform(elem[id].toLocaleLowerCase()).indexOf(this.latinisePipe.transform(filter)) != -1) : value;
        } else {
            console.log('Init filter failed for values : ');
            console.log(value);
        }
    }
}

