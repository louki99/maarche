import { Component, OnInit } from '@angular/core';
import { UntypedFormGroup, UntypedFormBuilder, Validators } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { tap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

@Component({
    selector: 'app-registered-mail',
    templateUrl: './registered-mail.component.html',
    styleUrls: ['./registered-mail.component.scss']
})
export class RegisteredMailComponent implements OnInit {

    creationMode: boolean;
    loading: boolean = true;

    adminFormGroup: UntypedFormGroup;
    id: number = null;
    minRange: number = 1;

    registeredMailType: any[] = [
        {
            id: '2D',
            label: this.translate.instant('lang.registeredMail_2D')
        },
        {
            id: '2C',
            label: this.translate.instant('lang.registeredMail_2C')
        },
        {
            id: 'RW',
            label: this.translate.instant('lang.registeredMail_RW')
        }
    ];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private _formBuilder: UntypedFormBuilder,
    ) { }

    ngOnInit(): void {
        this.route.params.subscribe(async (params) => {
            if (typeof params['id'] === 'undefined') {
                this.creationMode = true;
                this.headerService.setHeader(this.translate.instant('lang.registeredMailNumberRangeCreation'));

                this.adminFormGroup = this._formBuilder.group({
                    id: [null],
                    trackerNumber: [null, Validators.required],
                    registeredMailType: [null, Validators.required],
                    rangeStart: [1, Validators.required],
                    rangeEnd: [2, Validators.required],
                    status: ['SPD']
                });
                this.loading = false;

            } else {
                this.headerService.setHeader(this.translate.instant('lang.registeredMailNumberRangeModification'));
                this.id = params['id'];
                this.creationMode = false;
                await this.getData();
            }

            this.adminFormGroup.controls['registeredMailType'].valueChanges.pipe(
                tap(() => {
                    if (this.creationMode) {
                        this.getMinRange();
                    }
                })
            ).subscribe();

            this.adminFormGroup.controls['rangeStart'].valueChanges.pipe(
                tap((value: string) => {
                    if (value > this.adminFormGroup.controls['rangeEnd'].value) {
                        this.adminFormGroup.controls['rangeStart'].setErrors({'rangeError': true});
                    } else {
                        this.adminFormGroup.controls['rangeStart'].setErrors(null);
                        this.adminFormGroup.controls['rangeEnd'].setErrors(null);
                    }
                })
            ).subscribe();

            this.adminFormGroup.controls['rangeEnd'].valueChanges.pipe(
                tap((value: string) => {
                    if (value < this.adminFormGroup.controls['rangeStart'].value) {
                        this.adminFormGroup.controls['rangeEnd'].setErrors({'rangeError': true});
                    } else {
                        this.adminFormGroup.controls['rangeStart'].setErrors(null);
                        this.adminFormGroup.controls['rangeEnd'].setErrors(null);
                    }
                })
            ).subscribe();
        });
    }

    getData() {
        return new Promise((resolve) => {
            this.http.get(`../rest/registeredMail/ranges/${this.id}`).pipe(
                tap((data: any) => {
                    this.adminFormGroup = this._formBuilder.group({
                        id: [this.id],
                        trackerNumber: [data.range.trackerNumber],
                        registeredMailType: [data.range.registeredMailType],
                        rangeStart: [data.range.rangeStart],
                        rangeEnd: [data.range.rangeEnd],
                        status: [data.range.status]
                    });
                    if (data.range.status === 'OK' || data.range.status === 'END') {
                        this.adminFormGroup.disable();
                    }
                    resolve(true);
                    this.loading = false;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    onlyNumbers(event) {
        let k: number = 0;
        k = event.charCode;
        if (this.adminFormGroup.controls['rangeStart'].value === null && k === 48) {
            return false;
        } else {
            return (k >= 48 && k <= 57);
        }
    }

    getMinRange() {
        this.http.get(`../rest/registeredMail/ranges/type/${this.adminFormGroup.controls['registeredMailType'].value}/last`).pipe(
            tap((data: any) => {
                if (data.lastNumber === 1) {
                    this.minRange = data.lastNumber;
                } else {
                    this.minRange = data.lastNumber + 1;
                }
                this.adminFormGroup.controls['rangeStart'].setValue(this.minRange);
                if (this.adminFormGroup.controls['rangeEnd'].value < this.adminFormGroup.controls['rangeStart'].value) {
                    this.adminFormGroup.controls['rangeEnd'].setValue(this.adminFormGroup.controls['rangeStart'].value + 1);
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    onSubmit() {
        const objToSubmit = {};
        Object.keys(this.adminFormGroup.controls).forEach(key => {
            objToSubmit[key] = this.adminFormGroup.controls[key].value;
        });

        if (this.creationMode) {
            this.http.post('../rest/registeredMail/ranges', objToSubmit)
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.registeredMailNumberRangesAdded'));
                    this.router.navigate(['/administration/registeredMails']);
                }, (err) => {
                    this.notify.handleSoftErrors(err);
                });
        } else {
            this.http.put('../rest/registeredMail/ranges/' + this.id, objToSubmit)
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.registeredMailNumberRangesUpdated'));
                    this.router.navigate(['/administration/registeredMails']);
                }, (err) => {
                    this.notify.handleSoftErrors(err);
                });
        }
    }

}
