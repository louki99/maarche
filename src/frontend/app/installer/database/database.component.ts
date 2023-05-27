import { Component, OnInit, Output, EventEmitter } from '@angular/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators, ValidatorFn } from '@angular/forms';
import { NotificationService } from '@service/notification/notification.service';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { StepAction } from '../types';
import { FunctionsService } from '@service/functions.service';
import { InstallerService } from '../installer.service';
import { catchError, tap } from 'rxjs/operators';

@Component({
    selector: 'app-database',
    templateUrl: './database.component.html',
    styleUrls: ['./database.component.scss']
})
export class DatabaseComponent implements OnInit {

    @Output() nextStep = new EventEmitter<string>();

    stepFormGroup: UntypedFormGroup;
    hide: boolean = true;

    connectionState: boolean = false;
    dbExist: boolean = false;

    dataFiles: string[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private _formBuilder: UntypedFormBuilder,
        private notify: NotificationService,
        private functionsService: FunctionsService,
        private installerService: InstallerService
    ) {
        const valDbName: ValidatorFn[] = [Validators.pattern(/^[^\;\" \\]+$/), Validators.required];
        const valLoginDb: ValidatorFn[] = [Validators.pattern(/^[^ ]+$/), Validators.required];

        this.stepFormGroup = this._formBuilder.group({
            dbHostCtrl: ['localhost', Validators.required],
            dbLoginCtrl: ['', valLoginDb],
            dbPortCtrl: ['5432', Validators.required],
            dbPasswordCtrl: ['', valLoginDb],
            dbNameCtrl: ['', valDbName],
            dbSampleCtrl: ['data_fr', Validators.required],
            stateStep: ['', Validators.required]
        });
    }

    ngOnInit(): void {
        this.stepFormGroup.controls['dbHostCtrl'].valueChanges.pipe(
            tap(() => this.stepFormGroup.controls['stateStep'].setValue(''))
        ).subscribe();
        this.stepFormGroup.controls['dbLoginCtrl'].valueChanges.pipe(
            tap(() => this.stepFormGroup.controls['stateStep'].setValue(''))
        ).subscribe();
        this.stepFormGroup.controls['dbPortCtrl'].valueChanges.pipe(
            tap(() => this.stepFormGroup.controls['stateStep'].setValue(''))
        ).subscribe();
        this.stepFormGroup.controls['dbPasswordCtrl'].valueChanges.pipe(
            tap(() => this.stepFormGroup.controls['stateStep'].setValue(''))
        ).subscribe();
        this.stepFormGroup.controls['dbNameCtrl'].valueChanges.pipe(
            tap(() => this.stepFormGroup.controls['stateStep'].setValue(''))
        ).subscribe();

        this.getDataFiles();
    }

    getDataFiles() {
        this.http.get('../rest/installer/sqlDataFiles').pipe(
            tap((data: any) => {
                this.dataFiles = data.dataFiles;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidConnection() {
        return false;
    }

    initStep() {
        if (this.installerService.isStepAlreadyLaunched('database')) {
            this.stepFormGroup.disable();
        }
    }

    checkConnection() {

        const info = {
            server: this.stepFormGroup.controls['dbHostCtrl'].value,
            port: this.stepFormGroup.controls['dbPortCtrl'].value,
            user: this.stepFormGroup.controls['dbLoginCtrl'].value,
            password: this.stepFormGroup.controls['dbPasswordCtrl'].value,
            name: this.stepFormGroup.controls['dbNameCtrl'].value
        };

        this.http.get('../rest/installer/databaseConnection', { observe: 'response', params: info }).pipe(
            tap((data: any) => {
                this.dbExist = data.status === 200;
                this.notify.success(this.translate.instant('lang.rightInformations'));
                this.stepFormGroup.controls['stateStep'].setValue('success');
                this.nextStep.emit();
            }),
            catchError((err: any) => {
                this.dbExist = false;
                if (err.error.errors === 'Given database has tables') {
                    this.notify.error(this.translate.instant('lang.dbNotEmpty'));
                } else {
                    this.notify.error(this.translate.instant('lang.badInformations'));
                }
                this.stepFormGroup.markAllAsTouched();
                this.stepFormGroup.controls['stateStep'].setValue('');
                return of(false);
            })
        ).subscribe();
    }

    checkStep() {
        return this.stepFormGroup.valid;
    }

    isValidStep() {
        if (this.installerService.isStepAlreadyLaunched('database')) {
            return true;
        } else {
            return this.stepFormGroup === undefined ? false : this.stepFormGroup.valid;
        }
    }

    isEmptyConnInfo() {
        return this.stepFormGroup.controls['dbHostCtrl'].invalid ||
            this.stepFormGroup.controls['dbPortCtrl'].invalid ||
            this.stepFormGroup.controls['dbLoginCtrl'].invalid ||
            this.stepFormGroup.controls['dbPasswordCtrl'].invalid ||
            this.stepFormGroup.controls['dbNameCtrl'].invalid;
    }

    getFormGroup() {
        return this.installerService.isStepAlreadyLaunched('database') ? true : this.stepFormGroup;
    }

    getInfoToInstall(): StepAction[] {
        return [{
            idStep : 'database',
            body: {
                server: this.stepFormGroup.controls['dbHostCtrl'].value,
                port: this.stepFormGroup.controls['dbPortCtrl'].value,
                user: this.stepFormGroup.controls['dbLoginCtrl'].value,
                password: this.stepFormGroup.controls['dbPasswordCtrl'].value,
                name: this.stepFormGroup.controls['dbNameCtrl'].value,
                data: this.stepFormGroup.controls['dbSampleCtrl'].value
            },
            route : {
                method : 'POST',
                url : '../rest/installer/database'
            },
            description: this.translate.instant('lang.stepDatabaseActionDesc'),
            installPriority: 2
        }];
    }

}
