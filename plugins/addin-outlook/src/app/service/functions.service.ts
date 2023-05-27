import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { LatinisePipe } from 'ngx-pipes';

@Injectable({
    providedIn: 'root'
})
export class FunctionsService {

    constructor(
        public translate: TranslateService,
        private latinisePipe: LatinisePipe,
    ) { }

    empty(value: any) {
        if (value === null || value === undefined) {
            return true;

        } else if (Array.isArray(value)) {
            if (value.length > 0) {
                return false;
            } else {
                return true;
            }
        } else if (String(value) !== '') {
            return false;
        } else {
            return true;
        }
    }

    isDate(value: any) {
        return value instanceof Date && !isNaN(value.valueOf());
    }

    formatFrenchDateToTechnicalDate(date: string) {
        if (!this.empty(date)) {
            let arrDate = date.split('-');
            arrDate = arrDate.concat(arrDate[arrDate.length - 1].split(' '));
            arrDate.splice(2, 1);

            if (this.empty(arrDate[3])) {
                arrDate[3] = '00:00:00';
            }

            const formatDate = `${arrDate[2]}-${arrDate[1]}-${arrDate[0]} ${arrDate[3]}`;

            return formatDate;
        } else {
            return date;
        }
    }

    formatFrenchDateToObjectDate(date: string, delimiter: string = '-') {
        if (!this.empty(date)) {
            let arrDate = date.split(delimiter);
            arrDate = arrDate.concat(arrDate[arrDate.length - 1].split(' '));
            arrDate.splice(2, 1);

            if (this.empty(arrDate[3])) {
                arrDate[3] = '00:00:00';
            }

            const formatDate = `${arrDate[2]}-${arrDate[1]}-${arrDate[0]} ${arrDate[3]}`;

            return new Date(formatDate);
        } else {
            return date;
        }
    }

    formatDateObjectToDateString(date: Date, limitMode: boolean = false, format: string = 'dd-mm-yyyy') {
        if (date !== null) {
            let formatDate: any[] = [];
            format.split('-').forEach((element: any) => {
                if (element === 'dd') {
                    let day: any = date.getDate();
                    day = ('00' + day).slice(-2);
                    formatDate.push(day);
                } else if (element === 'mm') {
                    let month: any = date.getMonth() + 1;
                    month = ('00' + month).slice(-2);
                    formatDate.push(month);
                } else if (element === 'yyyy') {
                    let year: any = date.getFullYear();
                    formatDate.push(year);
                }
            });

            let limit = '';
            if (limitMode) {
                limit = ' 23:59:59';
            }
            return `${formatDate.join('-')}${limit}`;
        } else {
            return date;
        }
    }

    formatObjectToDateFullFormat(date: Date) {
        return this.formatSerializedDateToDateString(date.toString()) + ' ' + date.getHours() + ':' + date.getMinutes();
    }

    formatSerializedDateToDateString(date: string) {
        return this.formatDateObjectToDateString(new Date(date));
    }

    listSortingDataAccessor(data: any, sortHeaderId: any) {
        if (typeof data[sortHeaderId] === 'string') {
            return data[sortHeaderId].toLowerCase();
        }
        return data[sortHeaderId];
    }

    filterUnSensitive(template: any, filter: string, filteredColumns: any) {
        let filterReturn = false;
        filter = this.latinisePipe.transform(filter);
        filteredColumns.forEach((column: any) => {
            let val = template[column];
            if (typeof template[column] !== 'string') {
                val = val === undefined || null ? '' : JSON.stringify(val);
            }
            filterReturn = filterReturn || this.latinisePipe.transform(val.toLowerCase()).includes(filter);
        });
        return filterReturn;
    }

    formatBytes(bytes: number, decimals = 2) {
        if (typeof bytes === 'number') {
            if (bytes === 0) { return '0 Octet'; }

            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Octets', 'KO', 'MO', 'GO', 'TO', 'PO', 'EO', 'ZO', 'YO'];

            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        } else {
            return bytes;
        }
    }
}
