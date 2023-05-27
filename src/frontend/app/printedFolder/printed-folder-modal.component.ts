import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { map, tap, catchError, finalize } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { UntypedFormControl } from '@angular/forms';
import { SortPipe } from '@plugins/sorting.pipe';
import { SummarySheetComponent } from '../list/summarySheet/summary-sheet.component';
import { of } from 'rxjs';


@Component({
    templateUrl: 'printed-folder-modal.component.html',
    styleUrls: ['printed-folder-modal.component.scss'],
    providers: [SortPipe]
})
export class PrintedFolderModalComponent implements OnInit {
    loading: boolean = true;

    document: any[] = [];

    mainDocument: boolean = false;
    summarySheet: boolean = false;
    withSeparator: boolean = false;
    isLoadingResults: boolean = false;

    mainDocumentInformation: any = {};

    printedFolderElement: any = {
        attachments: [],
        notes: [],
        emails: [],
        acknowledgementReceipts: [],
        linkedResources : [],
        linkedResourcesAttachments : [],
    };

    selectedPrintedFolderElement: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<PrintedFolderModalComponent>,
        public functions: FunctionsService,
        private sortPipe: SortPipe,
        public dialog: MatDialog) {
    }

    async ngOnInit(): Promise<void> {
        Object.keys(this.printedFolderElement).forEach(element => {
            this.selectedPrintedFolderElement[element] = new UntypedFormControl({ value: [], disabled: false });
        });

        if (!this.data.multiple) {
            this.getMainDocInfo();
            this.getAttachments();
            this.getEmails();
            this.getAcknowledgementReceips();
            this.getNotes();
            await this.getLinkedResources();
        }

        this.loading = false;
    }

    getMainDocInfo() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}/fileInformation`).pipe(
                map((data: any) => {
                    data = {
                        ...data.information,
                        id: this.data.resId,
                    };
                    return data;
                }),
                tap((data) => {
                    this.mainDocumentInformation = data;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getAttachments() {
        return new Promise((resolve) => {
            this.http.get('../rest/resources/' + this.data.resId + '/attachments').pipe(
                map((data: any) => {
                    data.attachments = data.attachments.map((attachment: any) => ({
                        id: attachment.resId,
                        label: attachment.title,
                        chrono: !this.functions.empty(attachment.chrono) ? attachment.chrono : this.translate.instant('lang.undefined'),
                        type: attachment.typeLabel,
                        creationDate: attachment.creationDate,
                        canConvert: attachment.canConvert,
                        status: attachment.status
                    }));
                    return data.attachments;
                }),
                tap((data) => {

                    this.printedFolderElement.attachments = this.sortPipe.transform(data, 'chrono');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getLinkedResources() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}/linkedResources`).pipe(
                tap(async (data: any) => {
                    for (let index = 0; index < data.linkedResources.length; index++) {
                        this.printedFolderElement.linkedResources.push({
                            id: data.linkedResources[index].resId,
                            label: data.linkedResources[index].subject,
                            chrono: !this.functions.empty(data.linkedResources[index].chrono) ? data.linkedResources[index].chrono : this.translate.instant('lang.undefined'),
                            creationDate: data.linkedResources[index].documentDate,
                            canConvert: data.linkedResources[index].canConvert
                        });
                        await this.getLinkedAttachments(data.linkedResources[index]);
                    }
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getLinkedAttachments(resourceMaster: any) {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${resourceMaster.resId}/attachments`).pipe(
                map((data: any) => {
                    data.attachments = data.attachments.map((attachment: any) => ({
                        id: attachment.resId,
                        label: attachment.title,
                        resIdMaster : resourceMaster.resId,
                        chronoMaster: resourceMaster.chrono,
                        chrono: !this.functions.empty(attachment.chrono) ? attachment.chrono : this.translate.instant('lang.undefined'),
                        type: attachment.typeLabel,
                        creationDate: attachment.creationDate,
                        canConvert: attachment.canConvert,
                        status: attachment.status
                    }));
                    return data.attachments;
                }),
                tap((data) => {
                    this.printedFolderElement.linkedResourcesAttachments = this.printedFolderElement.linkedResourcesAttachments.concat(this.sortPipe.transform(data, 'chronoMaster'));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getEmails() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}/emails?type=email`).pipe(
                map((data: any) => {
                    data.emails = data.emails.map((item: any) => ({
                        id: item.id,
                        recipients: item.recipients,
                        creationDate: item.creation_date,
                        label: !this.functions.empty(item.object) ? item.object : `<i>${this.translate.instant('lang.emptySubject')}<i>`,
                        canConvert: true
                    }));
                    return data.emails;
                }),
                tap((data: any) => {
                    this.printedFolderElement.emails = this.sortPipe.transform(data, 'creationDate');

                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getNotes() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}/notes`).pipe(
                map((data: any) => {
                    data.notes = data.notes.map((item: any) => ({
                        id: item.id,
                        creator: `${item.firstname} ${item.lastname}`,
                        creationDate: item.creation_date,
                        label: item.value,
                        canConvert: true
                    }));
                    return data.notes;
                }),
                tap((data: any) => {
                    this.printedFolderElement.notes = this.sortPipe.transform(data, 'creationDate');

                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getAcknowledgementReceips() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}/acknowledgementReceipts?type=ar`).pipe(
                map((data: any) => {
                    data = data.map((item: any) => {
                        let email;
                        if (!this.functions.empty(item.contact.email)) {
                            email = item.contact.email;
                        } else {
                            email = this.translate.instant('lang.contactDeleted');
                        }
                        let name;
                        if (!this.functions.empty(item.contact.firstname) && !this.functions.empty(item.contact.lastname)) {
                            name = `${item.contact.firstname} ${item.contact.lastname}`;
                        } else {
                            name = this.translate.instant('lang.contactDeleted');
                        }

                        return {
                            id: item.id,
                            sender: false,
                            recipients: item.format === 'html' ? email : name,
                            creationDate: item.creationDate,
                            label: item.format === 'html' ? this.translate.instant('lang.ARelectronic') : this.translate.instant('lang.ARPaper'),
                            canConvert: true
                        };
                    });
                    return data;
                }),
                tap((data: any) => {
                    this.printedFolderElement.acknowledgementReceipts = this.sortPipe.transform(data, 'creationDate');

                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    toggleAllElements(state: boolean, type: any) {

        if (state) {
            this.selectedPrintedFolderElement[type].setValue(this.data.multiple ? 'ALL' : this.printedFolderElement[type].filter((item: any) => item.canConvert).map((item: any) => item.id));
        } else {
            this.selectedPrintedFolderElement[type].setValue([]);
        }
    }

    onSubmit() {
        this.isLoadingResults = true;

        this.http.post('../rest/resources/folderPrint', this.formatPrintedFolder(), { responseType: 'blob' }).pipe(
            tap((data: any) => {
                const downloadLink = document.createElement('a');
                downloadLink.href = window.URL.createObjectURL(data);
                downloadLink.setAttribute('download', this.functions.getFormatedFileName(this.translate.instant('lang.printedFolder'), this.data.multiple ? 'zip' : 'pdf'));
                document.body.appendChild(downloadLink);
                downloadLink.click();
            }),
            finalize(() => this.isLoadingResults = false),
            catchError((err: any) => {
                this.notify.handleBlobErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatPrintedFolder() {
        const printedFolder: any = {
            withSeparator: this.withSeparator,
            summarySheet: this.summarySheet,
            resources: []
        };
        let resource: any = null;

        this.data.resId.forEach((id: any, index: number) => {
            resource = {
                resId: id,
                document: this.mainDocument
            };
            if (!this.data.multiple) {
                Object.keys(this.printedFolderElement).forEach(element => {
                    if (this.selectedPrintedFolderElement[element].value.length !== this.printedFolderElement[element].length) {
                        resource[element] = this.selectedPrintedFolderElement[element].value;
                    } else {
                        resource[element] = this.selectedPrintedFolderElement[element].value.length > 0 ? 'ALL' : [];
                    }
                });
            } else {
                Object.keys(this.printedFolderElement).forEach(element => {
                    resource[element] = this.selectedPrintedFolderElement[element].value.length > 0 ? 'ALL' : [];
                });
            }
            printedFolder.resources.push(resource);
        });
        return printedFolder;
    }

    openSummarySheet(): void {

        const dialogRef = this.dialog.open(SummarySheetComponent, {
            panelClass: 'maarch-full-height-modal',
            width: '800px',
            data: {
                paramMode: true
            }
        });
        dialogRef.afterClosed().pipe(
            tap((data: any) => {
                this.summarySheet = data;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isEmptySelection() {
        let state = true;
        Object.keys(this.printedFolderElement).forEach(element => {
            if (this.selectedPrintedFolderElement[element].value.length > 0) {
                state = false;
            }
        });

        if (this.summarySheet || this.mainDocument) {
            state = false;
        }

        return state;
    }
}
