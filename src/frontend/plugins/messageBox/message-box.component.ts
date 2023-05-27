import { Component, OnInit } from '@angular/core';
import { Input } from '@angular/core';

@Component({
    selector: 'app-maarch-message',
    templateUrl: 'message-box.component.html',
    styleUrls: ['message-box.component.scss'],
})
export class MessageBoxComponent implements OnInit {

    /**
     * Style of alert
     */
    @Input() mode: 'info' | 'danger' | 'success' = 'info';

    /**
     * Content of alert box
     */
    @Input() content: string = null;

    constructor( ) { }

    ngOnInit() { }
}
