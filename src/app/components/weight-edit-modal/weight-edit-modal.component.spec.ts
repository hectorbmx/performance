import { ComponentFixture, TestBed, waitForAsync } from '@angular/core/testing';

import { WeightEditModalComponent } from './weight-edit-modal.component';

describe('WeightEditModalComponent', () => {
  let component: WeightEditModalComponent;
  let fixture: ComponentFixture<WeightEditModalComponent>;

  beforeEach(waitForAsync(() => {
    TestBed.configureTestingModule({
      imports: [WeightEditModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(WeightEditModalComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  }));

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
