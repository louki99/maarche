import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, debounceTime } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';
import { UntypedFormBuilder, UntypedFormGroup } from '@angular/forms';
import { NotificationService } from '@service/notification/notification.service';

@Component({
    selector: 'app-life-cyle',
    templateUrl: './life-cycle.component.html',
})

export class LifeCycleComponent implements OnInit {
    documentFinalAction: UntypedFormGroup;
    finalActionValues: any[] = ['restrictAccess', 'transfer', 'copy', 'delete'];

    constructor(public translate: TranslateService, public http: HttpClient, private _formBuilder: UntypedFormBuilder, private notify: NotificationService) {
        this.documentFinalAction = this._formBuilder.group({
            bindingDocumentFinalAction: [''],
            nonBindingDocumentFinalAction: ['']
        });
    }

    async ngOnInit(): Promise<void> {
        await this.getFinalAction();
    }

    getFinalAction() {
        return new Promise((resolve) => {
            this.http.get('../rest/parameters').pipe(
                tap((data: any) => {
                    const bindDocumentFinalAction = data.parameters.filter((item: { id: any }) => item.id === 'bindingDocumentFinalAction')[0].param_value_string;
                    const nonBindDocumentFinalAction = data.parameters.filter((item: { id: any }) => item.id === 'nonBindingDocumentFinalAction')[0].param_value_string;
                    this.documentFinalAction.controls['bindingDocumentFinalAction'].setValue(bindDocumentFinalAction);
                    this.documentFinalAction.controls['nonBindingDocumentFinalAction'].setValue(nonBindDocumentFinalAction);

                    setTimeout(() => {
                        this.documentFinalAction.controls['bindingDocumentFinalAction'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('bindingDocumentFinalAction'))
                        ).subscribe();

                        this.documentFinalAction.controls['nonBindingDocumentFinalAction'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('nonBindingDocumentFinalAction'))
                        ).subscribe();
                    });
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    saveParameter(id: string) {
        let param =  {};
        param = {
            param_value_string : this.documentFinalAction.controls[id].value
        };
        this.http.put('../rest/parameters/' + id, param)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.parameterUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

}
