import { Pipe, PipeTransform, NgZone, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

@Pipe({
    name: 'timeAgo',
    pure: false
})
export class TimeAgoPipe implements PipeTransform, OnDestroy {
    private timer: number;
    constructor(public translate: TranslateService, private changeDetectorRef: ChangeDetectorRef, private ngZone: NgZone) { }
    transform(value: string, args: string = null) {

        this.removeTimer();
        const d = new Date(value);
        const dayNumber = ('0' + d.getDate()).slice(-2);
        const realMonth = d.getMonth() + 1;
        const monthNumber = ('0' + realMonth).slice(-2);
        const hourNumber = ('0' + d.getHours()).slice(-2);
        const minuteNumber = ('0' + d.getMinutes()).slice(-2);
        const now = new Date();
        const month = [];
        month[0] = this.translate.instant('lang.januaryShort');
        month[1] = this.translate.instant('lang.februaryShort');
        month[2] = this.translate.instant('lang.marchShort');
        month[3] = this.translate.instant('lang.aprilShort');
        month[4] = this.translate.instant('lang.mayShort');
        month[5] = this.translate.instant('lang.juneShort');
        month[6] = this.translate.instant('lang.julyShort');
        month[7] = this.translate.instant('lang.augustShort');
        month[8] = this.translate.instant('lang.septemberShort');
        month[9] = this.translate.instant('lang.octoberShort');
        month[10] = this.translate.instant('lang.novemberShort');
        month[11] = this.translate.instant('lang.decemberShort');
        const seconds = Math.round(Math.abs((now.getTime() - d.getTime()) / 1000));
        const curentDayNumber = ('0' + now.getDate()).slice(-2);
        const timeToUpdate = (Number.isNaN(seconds)) ? 1000 : this.getSecondsUntilUpdate(seconds) * 1000;
        this.timer = this.ngZone.runOutsideAngular(() => {
            if (typeof window !== 'undefined') {
                return window.setTimeout(() => {
                    this.ngZone.run(() => this.changeDetectorRef.markForCheck());
                }, timeToUpdate);
            }
            return null;
        });
        const minutes = Math.round(Math.abs(seconds / 60));
        const hours = Math.round(Math.abs(minutes / 60));
        const days = Math.round(Math.abs(hours / 24));
        const months = Math.round(Math.abs(days / 30.416));
        const years = Math.round(Math.abs(days / 365));
        if (value == this.translate.instant('lang.undefined')) {
            return this.translate.instant('lang.undefined');
        } else if (Number.isNaN(seconds)) {
            return '';
        } else if (seconds <= 45) {
            return this.getFormatedDate(this.translate.instant('lang.dateAgo').toLowerCase(), this.translate.instant('lang.fewSeconds'), args);
        } else if (seconds <= 90) {
            return this.getFormatedDate(this.translate.instant('lang.dateAgo').toLowerCase(), this.translate.instant('lang.oneMinute'), args);
        } else if (minutes <= 45) {
            return this.getFormatedDate(this.translate.instant('lang.dateAgo').toLowerCase(), minutes + ' ' + this.translate.instant('lang.minutes'), args);
        } else if (minutes <= 90) {
            return this.getFormatedDate(this.translate.instant('lang.dateAgo').toLowerCase(), this.translate.instant('lang.oneHour'), args);
        } else if (hours <= 24 && dayNumber === curentDayNumber) {
            return this.getFormatedDate(this.translate.instant('lang.at').toLowerCase(), hourNumber + ':' + minuteNumber, args);
            // return hours + ' heures';
        } else if (hours <= 24) {
            return this.getFormatedDate(this.translate.instant('lang.dateAgo').toLowerCase(), days + ' ' + this.translate.instant('lang.dayS'), args);
            // return 'un jour';
        } else if (days <= 5) {
            return this.getFormatedDate(this.translate.instant('lang.dateAgo').toLowerCase(), days + ' ' + this.translate.instant('lang.dayS'), args);
            // return days + ' jours';
        } else if (days <= 345) {
            return this.getFormatedDate(this.translate.instant('lang.dateTo').toLowerCase(), d.getDate() + ' ' + month[d.getMonth()], args);
            // return months + ' mois';
        } else if (days <= 545) {
            return this.getFormatedDate(this.translate.instant('lang.dateTo').toLowerCase(), dayNumber + '/' + monthNumber + '/' + d.getFullYear(), args);
            // return 'un an';
        } else { // (days > 545)
            return this.getFormatedDate(this.translate.instant('lang.dateTo').toLowerCase(), dayNumber + '/' + monthNumber + '/' + d.getFullYear(), args);
            // return years + ' ans';
        }
    }
    ngOnDestroy(): void {
        this.removeTimer();
    }


    getFormatedDate(prefix: string, content: string, mode: string) {
        if (mode === 'full') {
            return `${prefix} ${content}`;
        } else {
            return content;
        }
    }

    private removeTimer() {
        if (this.timer) {
            window.clearTimeout(this.timer);
            this.timer = null;
        }
    }
    private getSecondsUntilUpdate(seconds: number) {
        const min = 60;
        const hr = min * 60;
        const day = hr * 24;
        if (seconds < min) { // less than 1 min, update every 2 secs
            return 2;
        } else if (seconds < hr) { // less than an hour, update every 30 secs
            return 30;
        } else if (seconds < day) { // less then a day, update every 5 mins
            return 300;
        } else { // update every hour
            return 3600;
        }
    }
}
