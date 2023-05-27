import { Component, Inject, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { catchError, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { DateOptionModalComponent } from './dateOption/date-option-modal.component';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'signature-position.component.html',
    styleUrls: ['signature-position.component.scss'],
})
export class SignaturePositionComponent implements OnInit {

    loading: boolean = true;

    pages: number[] = [];

    currentUser: number = 0;
    currentPage: number = null;
    currentSignature: any = {
        positionX: 0,
        positionY: 0
    };

    workingAreaWidth: number = 0;
    workingAreaHeight: number = 0;
    formatList: any[] = [
        'dd/MM/y',
        'dd-MM-y',
        'dd.MM.y',
        'd MMM y',
        'd MMMM y',
    ];
    datefonts: any[] = [
        'Arial',
        'Verdana',
        'Helvetica',
        'Tahoma',
        'Times New Roman',
        'Courier New',
    ];

    size = {
        'Arial': 15,
        'Verdana': 13,
        'Helvetica': 13,
        'Tahoma': 13,
        'Times New Roman': 15,
        'Courier New': 13
    };
    signList: any[] = [];
    dateList: any[] = [];

    pdfContent: any = null;
    imgContent: any = null;

    today: Date = new Date();
    localDate = this.translate.instant('lang.langISO');
    resizing: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<SignaturePositionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private functions: FunctionsService
    ) { }

    ngOnInit(): void {
        this.currentPage = 1;
        this.getPageAttachment();
        this.getAllUnits();
    }

    getAllUnits() {
        this.data.workflow.forEach((user: any, index: number) => {
            if (user.signaturePositions?.length > 0) {
                this.signList = this.signList.concat(user.signaturePositions.filter((pos: any) => pos.resId === this.data.resource.resId && pos.mainDocument === this.data.resource.mainDocument).map((pos: any) => ({
                    ...pos,
                    sequence : index
                })));
            }
            if (user.datePositions?.length > 0) {
                this.dateList = this.dateList.concat(user.datePositions.filter((pos: any) => pos.resId === this.data.resource.resId && pos.mainDocument === this.data.resource.mainDocument).map((pos: any) => ({
                    ...pos,
                    sequence : index
                })));
            }
        });
    }

    onSubmit() {
        this.dialogRef.close(this.formatData());
    }

    getPageAttachment() {
        if (this.data.resource.mainDocument) {
            this.http.get(`../rest/resources/${this.data.resource.resId}/thumbnail/${this.currentPage}`).pipe(
                tap((data: any) => {
                    this.pages = Array.from({ length: data.pageCount }).map((_, i) => i + 1);
                    this.imgContent = 'data:image/png;base64,' + data.fileContent;
                    this.getImageDimensions(this.imgContent);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.get(`../rest/attachments/${this.data.resource.resId}/thumbnail/${this.currentPage}`).pipe(
                tap((data: any) => {
                    this.pages = Array.from({ length: data.pageCount }).map((_, i) => i + 1);
                    this.imgContent = 'data:image/png;base64,' + data.fileContent;
                    this.getImageDimensions(this.imgContent);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    getImageDimensions(imgContent: any): void {
        const img = new Image();
        img.onload = (data: any) => {
            this.workingAreaWidth = data.target.naturalWidth;
            this.workingAreaHeight = data.target.naturalHeight;
        };
        if (document.getElementsByClassName('signatureContainer')[0] !== undefined) {
            img.src = imgContent;
            document.getElementsByClassName('signatureContainer')[0].scrollTop = 0;
        }
    }

    moveSign(event: any) {
        const percentx = (event.x * 100) / this.workingAreaWidth;
        const percenty = (event.y * 100) / this.workingAreaHeight;
        this.signList.filter((item: any) => item.sequence === this.currentUser && item.page === this.currentPage)[0].positionX = percentx;
        this.signList.filter((item: any) => item.sequence === this.currentUser && item.page === this.currentPage)[0].positionY = percenty;
    }

    moveDate(event: any) {
        const percentx = (event.x * 100) / this.workingAreaWidth;
        const percenty = (event.y * 100) / this.workingAreaHeight;
        this.dateList.filter((item: any) => item.sequence === this.currentUser && item.page === this.currentPage)[0].positionX = percentx;
        this.dateList.filter((item: any) => item.sequence === this.currentUser && item.page === this.currentPage)[0].positionY = percenty;
    }

    onResizeDateStop(event: any, index: number) {
        this.dateList[index].height = (event.size.height * 100) / this.workingAreaHeight;
        this.dateList[index].width = (event.size.width * 100) / this.workingAreaWidth;
    }

    emptySign() {
        return this.signList.filter((item: any) => item.sequence === this.currentUser && item.page === this.currentPage).length === 0;
    }

    emptyDate() {
        return this.dateList.filter((item: any) => item.sequence === this.currentUser && item.page === this.currentPage).length === 0;
    }

    initSign() {
        this.signList.push(
            {
                sequence: this.currentUser,
                page: this.currentPage,
                positionX: 0,
                positionY: 0
            }
        );
        document.getElementsByClassName('signatureContainer')[0].scrollTo(0, 0);
    }

    initDateBlock() {
        this.dateList.push(
            {
                sequence: this.currentUser,
                page: this.currentPage,
                font: 'Arial',
                size: 15,
                color: '#000000',
                format: 'd MMMM y',
                width: (130 * 100) / this.workingAreaWidth,
                height: (30 * 100) / this.workingAreaHeight,
                positionX: 0,
                positionY: 0
            }
        );
        document.getElementsByClassName('signatureContainer')[0].scrollTo(0, 0);
    }

    getUserSignPosPage(workflowIndex: number) {
        return this.signList.filter((item: any) => item.sequence === workflowIndex);
    }

    selectUser(workflowIndex: number) {
        this.currentUser = workflowIndex;
    }

    getUserName(workflowIndex: number) {
        return this.data.workflow[workflowIndex].labelToDisplay;
    }

    goToSignUserPage(workflowIndex: number, page: number) {
        this.currentUser = workflowIndex;
        this.currentPage = page;
        this.getPageAttachment();
    }

    imgLoaded() {
        this.loading = false;
    }

    deleteSign(index: number) {
        this.signList.splice(index, 1);
    }

    deleteDate(index: number) {
        this.dateList.splice(index, 1);
    }

    formatData() {
        const objToSend: any = {
            signaturePositions: [],
            datePositions: []
        };
        this.data.workflow.forEach((element: any, index: number) => {
            if (this.signList.filter((item: any) => item.sequence === index).length > 0) {
                objToSend['signaturePositions'] = objToSend['signaturePositions'].concat(this.signList.filter((item: any) => item.sequence === index));
            }
            if (this.dateList.filter((item: any) => item.sequence === index).length > 0) {
                objToSend['datePositions'] = objToSend['datePositions'].concat(this.dateList.filter((item: any) => item.sequence === index));
            }
        });
        return objToSend;
    }

    getUserPages() {
        const allList = this.signList.concat(this.dateList);

        return allList;
    }

    hasSign(userSequence: number, page: number) {
        return this.signList.filter((item: any) => item.sequence === userSequence && item.page === page).length > 0;
    }

    hasDate(userSequence: number, page: number) {
        return this.dateList.filter((item: any) => item.sequence === userSequence && item.page === page).length > 0;
    }

    openDateSettings(index: number) {
        const dialogRef = this.dialog.open(DateOptionModalComponent, {
            panelClass: 'maarch-modal',
            // disableClose: true,
            width: '500px',
            data: {
                currentDate : this.dateList[index]
            }
        });
        dialogRef.afterClosed().pipe(
            filter((res: any) => !this.functions.empty(res)),
            tap((res: any) => {
                this.dateList[index] = res;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkExternalUser() {
        if (this.data.workflow[this.currentUser].item_id !== null) {
            return true;
        } else {
            return this.data.workflow[this.currentUser]?.externalInformations.type !== 'fast';
        }
    }
}
