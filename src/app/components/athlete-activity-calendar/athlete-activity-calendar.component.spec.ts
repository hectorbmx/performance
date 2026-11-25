import { TestBed } from '@angular/core/testing';
import { AthleteActivityCalendarComponent } from './athlete-activity-calendar.component';
describe('Athlete activity calendar DOM stability',()=>{
  it('keeps day and icon nodes stable across change detection and still navigates',async()=>{
    await TestBed.configureTestingModule({imports:[AthleteActivityCalendarComponent]}).compileComponents();
    const fixture=TestBed.createComponent(AthleteActivityCalendarComponent);fixture.componentRef.setInput('month','2026-11');
    fixture.componentRef.setInput('days',[{date:'2026-11-15',status:'completed'}]);fixture.detectChanges();
    const first=fixture.nativeElement.querySelector('.status-completed');const icon=first.querySelector('ion-icon');
    for(let i=0;i<40;i++)fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('.status-completed')).toBe(first);expect(first.querySelector('ion-icon')).toBe(icon);
    const selected=spyOn(fixture.componentInstance.daySelected,'emit');first.click();expect(selected).toHaveBeenCalled();
    const month=spyOn(fixture.componentInstance.monthChange,'emit');fixture.nativeElement.querySelector('[aria-label="Mes siguiente"]').click();
    expect(month).toHaveBeenCalledWith('2026-12');fixture.componentRef.setInput('month','2026-12');fixture.detectChanges();
    expect(fixture.nativeElement.querySelectorAll('.day-cell').length).toBeLessThanOrEqual(42);fixture.destroy();
  });
});
