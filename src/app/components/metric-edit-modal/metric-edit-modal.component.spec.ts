import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { MetricEditModalComponent } from './metric-edit-modal.component';

describe('MetricEditModalComponent', () => {
  let component: MetricEditModalComponent;
  let fixture: ComponentFixture<MetricEditModalComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      imports: [MetricEditModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(MetricEditModalComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }));

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
