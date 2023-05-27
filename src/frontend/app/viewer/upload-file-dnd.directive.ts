import { Directive, Output, EventEmitter, HostBinding, HostListener, Input } from '@angular/core';

@Directive({
    selector: '[appUploadFileDragDrop]'
})
export class DragDropDirective {

    @Output() fileDropped = new EventEmitter<any>();
    @Input() disabled: boolean = false;

    @HostBinding('style.background-color') private background = 'none';
    @HostBinding('style.opacity') private opacity = '1';

    // Dragover listener
    @HostListener('dragover', ['$event']) onDragOver(evt: any) {
        if (!this.disabled) {
            evt.preventDefault();
            evt.stopPropagation();
            this.background = '#9ecbec';
            this.opacity = '0.8';
        }

    }

    // Dragleave listener
    @HostListener('dragleave', ['$event']) public onDragLeave(evt: any) {
        if (!this.disabled) {
            evt.preventDefault();
            evt.stopPropagation();
            this.background = 'rgba(255,255,255,0)';
            this.opacity = '1';
        }

    }

    // Drop listener
    @HostListener('drop', ['$event']) public ondrop(evt: any) {
        if (!this.disabled) {
            evt.preventDefault();
            evt.stopPropagation();
            this.background = 'rgba(255,255,255,0)';
            this.opacity = '1';
            const files = evt.dataTransfer.files;
            if (files.length > 0) {
                this.fileDropped.emit(files);
            }
        }

    }

}
