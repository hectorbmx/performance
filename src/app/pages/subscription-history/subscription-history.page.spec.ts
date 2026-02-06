import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SubscriptionHistoryPage } from './subscription-history.page';

describe('SubscriptionHistoryPage', () => {
  let component: SubscriptionHistoryPage;
  let fixture: ComponentFixture<SubscriptionHistoryPage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(SubscriptionHistoryPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
