import { ComponentFixture, TestBed } from '@angular/core/testing';
import { TrainingDetailsPage } from './training-details.page';

describe('TrainingDetailsPage', () => {
  let component: TrainingDetailsPage;
  let fixture: ComponentFixture<TrainingDetailsPage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(TrainingDetailsPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
