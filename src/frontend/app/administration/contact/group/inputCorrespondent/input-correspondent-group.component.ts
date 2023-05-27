import { Component, ElementRef, EventEmitter, Input, OnInit, Output, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { COMMA, ENTER } from '@angular/cdk/keycodes';
import { UntypedFormControl } from '@angular/forms';
import { MatAutocompleteSelectedEvent, MatAutocomplete } from '@angular/material/autocomplete';
import { Observable, of } from 'rxjs';
import { catchError, filter, finalize, map, tap } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { SortPipe } from '@plugins/sorting.pipe';

@Component({
    selector: 'app-input-correspondent-group',
    templateUrl: './input-correspondent-group.component.html',
    styleUrls: ['./input-correspondent-group.component.scss'],
    providers: [SortPipe]
})

export class InputCorrespondentGroupComponent implements OnInit {

    @ViewChild('correspondentGroupsInput') correspondentGroupsInput: ElementRef<HTMLInputElement>;
    @ViewChild('auto') matAutocomplete: MatAutocomplete;

    @Input() id: string;
    @Input() type: string;

    @Output() afterCorrespondentsGroupsLoaded = new EventEmitter<any>();

    visible = true;
    separatorKeysCodes: number[] = [ENTER, COMMA];
    correspondentGroupsForm = new UntypedFormControl();
    filteredcorrespondentGroups: Observable<string[]>;
    correspondentGroups: any[] = [];
    allCorrespondentGroups: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public headerService: HeaderService,
        public dialog: MatDialog,
        private sortPipe: SortPipe
    ) {
        this.filteredcorrespondentGroups = this.correspondentGroupsForm.valueChanges.pipe(
            map((item: any | null) => item ? this._filter(item) : this.allCorrespondentGroups));
    }
    async ngOnInit(): Promise<void> {
        await this.getAllCorrespondentsGroups();
        if (!this.functionsService.empty(this.id)) {
            await this.getCorrespondentsGroup();
        }
        this.fixInitAC();
    }


    fixInitAC() {
        this.correspondentGroupsForm.setValue(' ');
        setTimeout(() => {
            this.correspondentGroupsForm.setValue('');
        }, 100);
    }

    getAllCorrespondentsGroups() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsGroups').pipe(
                tap((data: any) => {
                    this.allCorrespondentGroups = data.contactsGroups.map((grp: any) => ({ id: grp.id, label: grp.label, canUpdateCorrespondents: grp.canUpdateCorrespondents }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getCorrespondentsGroup() {
        this.correspondentGroups = [];
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsGroupsCorrespondents', { params: { 'correspondentId': this.id, 'correspondentType': this.type } }).pipe(
                tap((data: any) => {
                    data.contactsGroups.forEach((grp: any) => {
                        this.correspondentGroups.push(grp);
                        this.sortPipe.transform(this.correspondentGroups, 'label');
                        const index = this.allCorrespondentGroups.map(cor => cor.id).indexOf(grp.id);
                        if (index > -1) {
                            this.allCorrespondentGroups.splice(index, 1);
                        }
                        this.allCorrespondentGroups = this.allCorrespondentGroups.filter((item: any) => item.canUpdateCorrespondents);
                    });
                    this.afterCorrespondentsGroupsLoaded.emit(this.correspondentGroups);
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    remove(item: any): void {
        if (!this.functionsService.empty(this.id)) {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.removeCorrespondentsGroup(item);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.removeCorrespondentsGroup(item);
        }
    }

    removeCorrespondentsGroup(item: any) {
        const index = this.correspondentGroups.map(cor => cor.id).indexOf(item.id);
        this.allCorrespondentGroups.push(item);
        this.sortPipe.transform(this.allCorrespondentGroups, 'label');
        if (index > -1) {
            this.correspondentGroups.splice(index, 1);
            if (!this.functionsService.empty(this.id)) {
                this.unlinkGrp(item.id);
            }
        }
    }

    selected(event: MatAutocompleteSelectedEvent): void {
        const element = this.allCorrespondentGroups.filter((item: any) => item.label === event.option.viewValue)[0];
        const index = this.allCorrespondentGroups.map(cor => cor.id).indexOf(element.id);
        this.correspondentGroups.push(element);
        if (index > -1) {
            this.allCorrespondentGroups.splice(index, 1);
        }
        this.correspondentGroupsInput.nativeElement.value = '';
        this.correspondentGroupsForm.setValue(null);
        if (!this.functionsService.empty(this.id)) {
            this.linkGrp(element.id);
        }
    }

    linkGrp(groupId: number, notification: boolean = true) {
        this.http.post('../rest/contactsGroups/' + groupId + '/correspondents', { 'correspondents': this.formatCorrespondents() })
            .subscribe((data: any) => {
                if (notification) {
                    this.notify.success(this.translate.instant('lang.correspondentAdded'));
                }
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    unlinkGrp(groupId: number) {

        this.http.request('DELETE', `../rest/contactsGroups/${groupId}/correspondents`, { body: { correspondents: this.formatCorrespondents() } }).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.contactDeletedFromGroup'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    linkGrpAfterCreation(id: string, type: string) {
        this.id = id;
        this.type = type;
        this.correspondentGroups.forEach((grp: any) => {
            this.linkGrp(grp.id, false);
        });
    }

    formatCorrespondents() {
        return [
            {
                id: this.id,
                type: this.type
            }
        ];
    }

    private _filter(value: any): string[] {
        return this.allCorrespondentGroups.filter((item: any) => item.label.toLowerCase().indexOf(value) > -1);
    }
}
