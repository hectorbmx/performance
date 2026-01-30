import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatePage } from './activate.page';

describe('ActivatePage', () => {
  let component: ActivatePage;
  let fixture: ComponentFixture<ActivatePage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(ActivatePage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
