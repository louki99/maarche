import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { JoyrideService } from 'ngx-joyride';
import { HeaderService } from './header.service';
import { FunctionsService } from './functions.service';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from './notification/notification.service';

@Injectable({
    providedIn: 'root'
})
export class FeatureTourService {

    currentStepType: string = '';

    currentTour: any = null;

    tour: any[] = [
        {
            type: 'welcome',
            stepId: 'welcome',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.welcomeTourTitle')}</b>`,
            description: this.translate.instant('lang.welcomeTourDescription'),
            redirectToAdmin: false,
        },
        {
            type: 'email',
            stepId: 'admin_email_server@administration',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.admin_email_serverTitle')}</b>`,
            description: this.translate.instant('lang.admin_email_serverTour'),
            redirectToAdmin: false,
        },
        {
            type: 'email',
            stepId: 'emailTour@administration/sendmail',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.emailTourTitle')}</b>`,
            description: this.translate.instant('lang.emailTourDescription'),
            redirectToAdmin: false,
        },
        {
            type: 'notification',
            stepId: 'admin_notif@administration',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.admin_notifTitle')}</b>`,
            description: this.translate.instant('lang.admin_notifTour'),
            redirectToAdmin: false,
        },
        {
            type: 'notification',
            stepId: 'BASKETS_Tour@administration/notifications',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.notifTour2Title')}</b>`,
            description: this.translate.instant('lang.notifTour2Description'),
            redirectToAdmin: false,
        },
        {
            type: 'notification',
            stepId: 'createScriptTour@administration/notifications/4',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.createScriptTourTitle')}</b>`,
            description: this.translate.instant('lang.createScriptTourDescription'),
            redirectToAdmin: false,
        },
        {
            type: 'notification',
            stepId: 'notifTour@administration/notifications',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.notifTourTitle')}</b>`,
            description: this.translate.instant('lang.notifTourDescription'),
            redirectToAdmin: false,
        },
        {
            type: 'notification',
            stepId: 'notifTour3@administration/notifications',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.notifTour3Title')}</b>`,
            description: this.translate.instant('lang.notifTour3Description'),
            redirectToAdmin: false,
        },
        {
            type: 'notification',
            stepId: 'notifTour4@administration/notifications',
            title: `<i class="far fa-question-circle" color="primary"></i>&nbsp;<b color="primary">${this.translate.instant('lang.notifTour4Title')}</b>`,
            description: this.translate.instant('lang.notifTour4Description'),
            redirectToAdmin: false,
        },
    ];

    featureTourEnd: any[] = [];

    constructor(
        public translate: TranslateService,
        private readonly joyrideService: JoyrideService,
        private headerService: HeaderService,
        private functionService: FunctionsService,
        private router: Router,
        private http: HttpClient,
        private notify: NotificationService,
    ) {
    }

    init() {
        this.getCurrentStepType();

        if (!this.functionService.empty(this.currentStepType)) {
            const steps = this.tour.filter(step => step.type === this.currentStepType).map(step => step.stepId);
            this.joyrideService.startTour(
                {
                    customTexts: {
                        next: '>>',
                        prev: '<<',
                        done: this.translate.instant('lang.getIt')
                    },
                    steps: steps,
                    waitingTime: 500
                }
            ).subscribe(
                step => {
                    setTimeout(() => {
                        /* Do something*/
                        this.currentTour = this.tour.filter((item: any) => item.stepId.split('@')[0] === step.name)[0];
                        const containerElement = document.getElementsByClassName('joyride-step__container') as HTMLCollectionOf<HTMLElement>;
                        if (containerElement && containerElement.length > 0) {
                            containerElement[0].style.width = 'auto';
                            containerElement[0].style.height = 'auto';
                        }

                        const headerElement = document.getElementsByClassName('joyride-step__header') as HTMLCollectionOf<HTMLElement>;
                        const bodyElement = document.getElementsByClassName('joyride-step__body') as HTMLCollectionOf<HTMLElement>;

                        if (headerElement && headerElement.length > 0) {
                            headerElement[0].innerHTML = `${this.currentTour.title}`;
                        }

                        if (bodyElement && bodyElement.length > 0) {
                            bodyElement[0].innerHTML = `${this.currentTour.description}`;
                        }
                    }, 10);
                },
                error => {
                    /* handle error*/
                },
                () => {
                    if (this.currentTour === null) {
                        this.router.navigate(['/home']);
                    } else if (this.currentTour.redirectToAdmin) {
                        this.router.navigate(['/administration']);
                    } else {
                        this.endTour();
                    }
                }
            );
        }
    }

    getCurrentStepType() {
        this.featureTourEnd = this.headerService.user.featureTour;
        this.currentStepType = this.getFeatureTourTypes().filter(stepType => this.featureTourEnd.indexOf(stepType) === -1)[0];
    }

    endTour() {
        if (this.currentStepType !== undefined) {
            this.featureTourEnd.push(this.currentStepType);
            this.http.put('../rest/currentUser/profile/featureTour', {featureTour : this.featureTourEnd}).pipe(
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
            this.getCurrentStepType();
        }
    }

    getFeatureTourTypes() {
        return [...new Set(this.tour.map(item => item.type))];
    }

    isComplete() {
        if (this.headerService.user.mode === 'root_visible' || this.headerService.user.mode === 'root_invisible') {
            return this.headerService.user.featureTour.length === this.getFeatureTourTypes().length;
        } else {
            return true;
        }
    }
}
