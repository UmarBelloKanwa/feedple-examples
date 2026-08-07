import os
from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, ForeignKey, func
from sqlalchemy.orm import declarative_base, sessionmaker

DATABASE_URL = os.getenv("DATABASE_URL", "sqlite:///./app.db")

engine = create_engine(
    DATABASE_URL,
    connect_args={"check_same_thread": False} if "sqlite" in DATABASE_URL else {}
)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    full_name = Column(String, nullable=False)
    email = Column(String, unique=True, index=True, nullable=False)
    created_at = Column(DateTime, server_default=func.now())

class Product(Base):
    __tablename__ = "products"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, nullable=False)
    category = Column(String, nullable=False)
    price = Column(Float, nullable=False)
    stock_quantity = Column(Integer, default=0)

class Order(Base):
    __tablename__ = "orders"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    product_id = Column(Integer, ForeignKey("products.id"), nullable=False)
    quantity = Column(Integer, default=1)
    total_amount = Column(Float, nullable=False)
    status = Column(String, default="completed")
    created_at = Column(DateTime, server_default=func.now())

def init_db():
    Base.metadata.create_all(bind=engine)
    db = SessionLocal()
    try:
        if db.query(Product).count() == 0:
            sample_products = [
                Product(name="Feedple AI Pro Plan", category="Subscription", price=49.99, stock_quantity=1000),
                Product(name="Developer SDK License", category="Software", price=199.00, stock_quantity=500),
                Product(name="Enterprise Analytics Module", category="Add-on", price=499.00, stock_quantity=100),
            ]
            db.add_all(sample_products)
            db.commit()

        if db.query(User).count() == 0:
            sample_users = [
                User(full_name="Alice Johnson", email="alice@example.com"),
                User(full_name="Bob Smith", email="bob@example.com"),
            ]
            db.add_all(sample_users)
            db.commit()

        if db.query(Order).count() == 0:
            sample_orders = [
                Order(user_id=1, product_id=1, quantity=1, total_amount=49.99, status="completed"),
                Order(user_id=2, product_id=2, quantity=2, total_amount=398.00, status="completed"),
            ]
            db.add_all(sample_orders)
            db.commit()
    finally:
        db.close()
